<?php

declare(strict_types=1);

namespace App\DTOs\Admin;

/**
 * Một ô trong ma trận quyền thao tác (module × hành động).
 *
 * Ô mang theo cả thông tin CỘT để view chỉ cần một vòng lặp — bản cũ phải lồng hai vòng rồi tra
 * ngược bằng `collect($module['actions'])->keyBy('action')` ở mỗi dòng.
 */
final readonly class PermissionMatrixCell
{
    /**
     * @param  bool  $available  module này có hành động đó không; không có thì view hiện dấu `—`
     * @param  bool  $checked  vai đang chọn đã có quyền này
     * @param  bool  $locked  vai `admin` không được bỏ quyền — ô khoá và gửi kèm input hidden
     */
    public function __construct(
        public string $action,
        public string $actionLabel,
        public bool $destructive,
        public bool $available,
        public string $permissionName,
        public string $description,
        public bool $checked,
        public bool $locked,
    ) {}
}
