<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

/**
 * Một ô KPI trạng thái trên `sites/index` (Tổng / Chuẩn bị / Đang lắp đặt / Đang bảo hành).
 *
 * Thay mảng `$kpi` khai trong khối `@php` thứ hai của view.
 */
final readonly class SiteKpiCard
{
    /**
     * @param  string  $valueText  đã ở dạng chữ vì bản cũ dùng `?:` — đếm bằng 0 thì in `—`,
     *                             riêng ô "Tổng công trình" in thẳng số kể cả khi bằng 0
     * @param  string  $toneClass  CHUỖI LỚP đầy đủ, không phải tên tông. Tailwind quét mã nguồn
     *                             theo văn bản nên `kpi-{{ $tone }}` ghép lúc chạy sẽ không được
     *                             sinh ra; phải là chuỗi tĩnh nằm sẵn trong mã.
     */
    public function __construct(
        public string $label,
        public string $valueText,
        public string $icon,
        public string $toneClass,
    ) {}
}
