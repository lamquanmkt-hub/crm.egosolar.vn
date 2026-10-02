<?php

declare(strict_types=1);

namespace App\Services\RolePermission;

/**
 * Danh mục quyền nghiệp vụ chuyên biệt khai báo trong `config/permissions.php`.
 *
 * File config đó là ma trận **role → quyền** kèm nhãn tiếng Việt, ví dụ
 * `order.approve_level1 => 'Duyệt đơn – Sales Manager (Bước 1)'`. Đây là những
 * thao tác mà bộ CRUD (xem/thêm/sửa/xoá) KHÔNG diễn tả được: duyệt nhiều bước,
 * đánh dấu đã thanh toán, kiểm kê kho, phân công lead, duyệt vượt cấp...
 *
 * Trước đây file này chỉ được dùng làm tài liệu tham khảo: quyền có tồn tại
 * trong DB hay không phụ thuộc việc ai đó nhớ tạo tay, và màn phân quyền hiển
 * thị tên kỹ thuật đã "humanize" chứ không dùng nhãn đã viết sẵn ở đây.
 *
 * Lớp này biến nó thành nguồn dữ liệu thật:
 * - `names()` cho lệnh đồng bộ tạo đủ quyền còn thiếu;
 * - `label()` cho giao diện hiển thị đúng tiếng Việt;
 * - `defaultsByRole()` để biết role nào lẽ ra có quyền gì.
 */
final class BusinessPermissionCatalog
{
    /** Ký hiệu "toàn quyền" của admin — không phải một quyền thật. */
    private const WILDCARD = '*';

    /**
     * Tên quyền => nhãn tiếng Việt (gộp từ mọi role, không trùng).
     *
     * @return array<string, string>
     */
    public function labelled(): array
    {
        $labels = [];

        foreach ($this->defaultsByRole() as $permissions) {
            foreach ($permissions as $name => $label) {
                // Nhãn đầu tiên gặp được giữ lại: các role sau thường mô tả
                // cùng một quyền theo góc nhìn của riêng họ.
                $labels[$name] ??= $label;
            }
        }

        ksort($labels);

        return $labels;
    }

    /**
     * Toàn bộ tên quyền nghiệp vụ đã khai báo.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->labelled());
    }

    /**
     * Nhãn tiếng Việt của một quyền (null nếu chưa khai báo).
     */
    public function label(string $permissionName): ?string
    {
        return $this->labelled()[$permissionName] ?? null;
    }

    /**
     * Ma trận role => [tên quyền => nhãn], đã loại ký hiệu toàn quyền `*`.
     *
     * @return array<string, array<string, string>>
     */
    public function defaultsByRole(): array
    {
        $matrix = [];

        foreach ((array) config('permissions', []) as $role => $permissions) {
            if (! is_array($permissions)) {
                continue;
            }

            $clean = [];

            foreach ($permissions as $name => $label) {
                $name = (string) $name;

                if ($name === self::WILDCARD || $name === '') {
                    continue;
                }

                $clean[$name] = (string) $label;
            }

            if ($clean !== []) {
                $matrix[(string) $role] = $clean;
            }
        }

        return $matrix;
    }

    /**
     * Quyền có trong DB nhưng KHÔNG được khai báo ở đâu cả.
     *
     * Dùng để phát hiện quyền "mồ côi": tạo tay trong lúc chữa cháy rồi quên,
     * hoặc còn sót sau khi đổi tên. Không tự xoá — role có thể đang dùng.
     *
     * @param  list<string>  $permissionNamesInDatabase
     * @param  list<string>  $managedActionNames  Quyền CRUD do ActionPermissionRegistry sinh
     * @return list<string>
     */
    public function undeclared(array $permissionNamesInDatabase, array $managedActionNames): array
    {
        $known = array_merge(
            $this->names(),
            $managedActionNames,
            array_keys((array) config('role_permissions.page_permissions', [])),
            array_keys((array) config('role_permissions.menu_permissions', [])),
        );

        $undeclared = array_values(array_diff($permissionNamesInDatabase, $known));

        sort($undeclared);

        return $undeclared;
    }
}
