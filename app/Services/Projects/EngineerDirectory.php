<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Danh sách người có thể làm kỹ sư phụ trách dự án / công trình.
 *
 * ## Vì sao là lớp riêng
 * Logic này trước nằm trong `UnifiedProjectController::engineers()` (private).
 * Khi form tạo công trình cũng cần đúng danh sách đó, chép sang là tạo bản thứ
 * hai của cùng một quy tắc vai trò — hai bên sẽ trôi lệch. Nay một bản dùng chung.
 *
 * ## Quy tắc lọc, giữ NGUYÊN VĂN từ bản cũ
 * Lấy người dùng đang hoạt động, rồi giữ ai mang một trong các vai trò kỹ thuật.
 * Nếu lọc xong KHÔNG còn ai thì trả về toàn bộ danh sách — chủ ý: thà cho chọn
 * rộng còn hơn đưa ra ô chọn rỗng khiến không tạo được công trình. Mọi lỗi khi
 * đọc vai trò cũng rơi về toàn bộ danh sách vì lý do đó.
 */
final class EngineerDirectory
{
    /** @var list<string> */
    private const TECHNICAL_ROLES = [
        'admin', 'management', 'director', 'general_director',
        'technical_manager', 'technical_leader', 'technical', 'technician',
        'engineer', 'engineering',
    ];

    /** @return Collection<int, User> */
    public function options(): Collection
    {
        $query = User::query()->select(['id', 'name', 'email']);

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', 1);
        }

        $users = $query->orderBy('name')->get();

        if (! method_exists(User::class, 'roles')) {
            return $users;
        }

        try {
            $users->load('roles:id,name');

            $filtered = $users->filter(fn ($user): bool => $this->isTechnical($user))->values();

            return $filtered->isNotEmpty() ? $filtered : $users;
        } catch (\Throwable $e) {
            return $users;
        }
    }

    private function isTechnical(User $user): bool
    {
        $roles = $user->roles?->pluck('name')->map(
            static fn ($role): string => strtolower((string) $role),
        )->all() ?? [];

        return array_intersect($roles, self::TECHNICAL_ROLES) !== [];
    }
}
