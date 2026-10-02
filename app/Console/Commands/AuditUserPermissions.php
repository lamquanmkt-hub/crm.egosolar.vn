<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Soi tình trạng phân quyền của người dùng — CHỈ ĐỌC, an toàn trên production.
 *
 * Trả lời ba câu hỏi hay gây sự cố:
 * 1. Ai KHÔNG có role nào? (từ 2026-08-05 những tài khoản này bị chặn mọi trang)
 * 2. Ai có `is_active = 0` nhưng VẪN đang dùng hệ thống? (dữ liệu is_active sai;
 *    bật `enforce_active_account_on_login` khi chưa dọn sẽ khoá nhầm người)
 * 3. Role nào chưa bật `page_access_enabled`? (role đó bỏ qua kiểm soát trang)
 *
 *   php artisan permissions:audit-users
 */
final class AuditUserPermissions extends Command
{
    protected $signature = 'permissions:audit-users {--days=30 : Số ngày coi là "còn hoạt động"}';

    protected $description = 'Báo cáo user thiếu role, user is_active sai và role chưa bật kiểm soát trang';

    public function handle(): int
    {
        $hasIsActive = SchemaCache::hasColumn('users', 'is_active');
        $hasLastSeen = SchemaCache::hasColumn('users', 'last_seen_at');
        $days = max(1, (int) $this->option('days'));
        $problems = 0;

        $problems += $this->reportUsersWithoutRole();

        if ($hasIsActive && $hasLastSeen) {
            $problems += $this->reportRecentlyActiveButDisabled($days);
        }

        $problems += $this->reportRolesWithoutPageControl();

        if ($problems === 0) {
            $this->components->info('Không phát hiện vấn đề phân quyền nào.');
        }

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** User không được gán role nào — hiện bị chặn toàn bộ trang. */
    private function reportUsersWithoutRole(): int
    {
        $users = User::doesntHave('roles')->orderBy('name')->get();

        if ($users->isEmpty()) {
            return 0;
        }

        $this->components->warn($users->count().' user KHÔNG có role nào (bị chặn mọi trang):');

        foreach ($users as $user) {
            $this->line(sprintf('  • #%-5d %-30s %s', $user->id, $user->name, $user->email));
        }

        $this->newLine();

        return 1;
    }

    /**
     * Tài khoản bị đánh dấu ngừng hoạt động nhưng vẫn truy cập gần đây.
     *
     * Đây là dữ liệu SAI, không phải người dùng sai: phải dọn trước khi bật
     * chặn đăng nhập theo `is_active`.
     */
    private function reportRecentlyActiveButDisabled(int $days): int
    {
        $users = User::query()
            ->where('is_active', 0)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subDays($days))
            ->orderByDesc('last_seen_at')
            ->get();

        if ($users->isEmpty()) {
            return 0;
        }

        $this->components->error(sprintf(
            '%d tài khoản is_active=0 nhưng VẪN dùng hệ thống trong %d ngày qua — bật chặn đăng nhập lúc này sẽ khoá nhầm:',
            $users->count(),
            $days,
        ));

        foreach ($users as $user) {
            $this->line(sprintf(
                '  • #%-5d %-30s truy cập lần cuối %s  (role: %s)',
                $user->id,
                $user->name,
                $user->last_seen_at,
                $user->roles->pluck('name')->implode(', ') ?: 'không có',
            ));
        }

        $this->newLine();

        return 1;
    }

    /** Role chưa bật kiểm soát quyền trang thì đi qua mọi trang. */
    private function reportRolesWithoutPageControl(): int
    {
        if (! SchemaCache::hasColumn('roles', 'page_access_enabled')) {
            return 0;
        }

        $roles = Role::query()
            ->where(function ($query): void {
                $query->whereNull('page_access_enabled')->orWhere('page_access_enabled', 0);
            })
            ->whereNotIn('name', (array) config('role_permissions.admin_roles', ['admin']))
            ->withCount('users')
            ->orderBy('name')
            ->get();

        if ($roles->isEmpty()) {
            return 0;
        }

        $this->components->warn($roles->count().' role CHƯA bật page_access_enabled (bỏ qua kiểm soát quyền trang):');

        foreach ($roles as $role) {
            $this->line(sprintf('  • %-24s (%d user)', $role->name, $role->users_count));
        }

        $this->newLine();

        return 1;
    }
}
