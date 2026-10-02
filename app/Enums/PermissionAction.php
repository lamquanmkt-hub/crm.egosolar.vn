<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bốn thao tác chuẩn trên mọi trang: xem, tạo, sửa, xoá.
 *
 * Trước đây quyền thao tác được đặt tên tuỳ hứng theo từng module — chỗ thì
 * `products.manage` gộp cả 4 việc, chỗ thì `payment.create`/`payment.view` thiếu
 * sửa/xoá, chỗ lại tách rất chi tiết như `maintenance.*` (17 quyền). Hệ quả:
 * màn "Quyền thao tác" hiện một danh sách phẳng không đối chiếu được, và không
 * ai trả lời được câu "role này có được xoá đơn hàng không".
 *
 * Enum này chốt bộ động từ dùng chung để mọi trang đều có đủ 4 quyền cùng tên.
 */
enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    /**
     * Nhãn tiếng Việt hiển thị trên giao diện phân quyền.
     */
    public function label(): string
    {
        return match ($this) {
            self::View => 'Xem',
            self::Create => 'Thêm',
            self::Update => 'Sửa',
            self::Delete => 'Xoá',
        };
    }

    /**
     * Mô tả đầy đủ cho tooltip/cột mô tả.
     */
    public function description(string $moduleLabel): string
    {
        return match ($this) {
            self::View => "Xem danh sách và chi tiết {$moduleLabel}",
            self::Create => "Thêm mới {$moduleLabel}",
            self::Update => "Chỉnh sửa {$moduleLabel}",
            self::Delete => "Xoá {$moduleLabel}",
        };
    }

    /**
     * Icon Bootstrap cho từng thao tác.
     */
    public function icon(): string
    {
        return match ($this) {
            self::View => 'bi-eye',
            self::Create => 'bi-plus-circle',
            self::Update => 'bi-pencil-square',
            self::Delete => 'bi-trash',
        };
    }

    /**
     * Thao tác này có phá huỷ dữ liệu không — dùng để cảnh báo trên giao diện.
     */
    public function isDestructive(): bool
    {
        return $this === self::Delete;
    }

    /**
     * Thứ tự hiển thị chuẩn: Xem → Thêm → Sửa → Xoá.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::View, self::Create, self::Update, self::Delete];
    }
}
