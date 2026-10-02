<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Danh sách "Trưởng phòng Sales" dùng cho các dropdown chọn người phụ trách.
 *
 * ## Vì sao tồn tại
 * Trước đây đoạn tính danh sách này được COPY nguyên văn trong 7 file Blade
 * (sidebar + 6 trang sales). Mỗi bản sao chạy `User::query()->get()` rồi gọi
 * `getRoleNames()` trong vòng lặp — N+1 kinh điển: mỗi user 1 truy vấn role.
 * Trang nào vừa có sidebar vừa có form thì chạy 2 lần. Đo trên DB test 37 user:
 * 44 truy vấn `model_has_roles` cho MỘT lần render trang.
 *
 * Ngoài ra bản cũ đọc `$u->role/type/position/department` như cột thường,
 * nhưng `position`/`department` là quan hệ BelongsTo — `(string) $u->department`
 * ném lỗi "Object of class Department could not be converted to string" ngay
 * khi gặp user CÓ phòng ban, và `try/catch (\Throwable)` bao ngoài nuốt lỗi
 * rồi trả về danh sách RỖNG. Tức là dropdown im lặng mất dữ liệu trên
 * production. Bản này đọc đúng `name` của quan hệ.
 *
 * ## Cách làm
 * Nạp user kèm `roles`, `department`, `position` bằng eager load (3 truy vấn cố
 * định, không phụ thuộc số user) rồi lọc trong bộ nhớ. Kết quả memo hoá theo
 * vòng đời request vì cùng một trang có thể hỏi nhiều lần.
 */
final class SalesManagerDirectory
{
    /**
     * Từ khoá nhận diện trưởng phòng sales (so khớp không phân biệt hoa thường).
     *
     * @var list<string>
     */
    private const MANAGER_NEEDLES = [
        'sales_manager',
        'sales manager',
        'trưởng phòng sales',
        'truong_phong_sales',
        'manager_sales',
    ];

    /** @var Collection<int, array{id: string, name: string}>|null */
    private ?Collection $cached = null;

    /**
     * Danh sách trưởng phòng sales dạng {id, name} để đổ ra JSON cho JS.
     *
     * @return Collection<int, array{id: string, name: string}>
     */
    public function options(): Collection
    {
        return $this->cached ??= User::query()
            ->select(['id', 'name', 'email', 'department_id', 'position_id'])
            ->with([
                'roles:id,name',
                'department:id,name',
                'position:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $this->isSalesManager($user))
            ->map(static fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => (string) ($user->name ?? $user->email ?? 'User #'.$user->id),
            ])
            ->values();
    }

    /**
     * User có phải trưởng phòng sales không — xét role Spatie, chức danh và phòng ban.
     */
    private function isSalesManager(User $user): bool
    {
        $haystack = mb_strtolower(implode('|', array_filter([
            $user->roles->pluck('name')->implode('|'),
            (string) ($user->position?->name ?? ''),
            (string) ($user->department?->name ?? ''),
        ])));

        if ($haystack === '') {
            return false;
        }

        foreach (self::MANAGER_NEEDLES as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
