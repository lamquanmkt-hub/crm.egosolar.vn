<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

/**
 * Một dòng bảng công trình (`sites/index`).
 *
 * Tồn tại vì view có khối `@php` **52 dòng ngay trong `@forelse`** — chạy lại cho từng dòng để
 * suy trạng thái, định dạng công suất/ngày, tách tên người phụ trách và tra số tài chính.
 *
 * Giữ NGUYÊN model ở `$site` thay vì trải hết thuộc tính: view còn đọc `name`, `address`, `note`,
 * `contact_name`, `contact_phone`, `company_id`, `id`, và `rules/blade-views.md` cho phép đọc
 * accessor của model. Tiền lệ cùng cách: `WarehouseItemRow`, `PaymentRequestAttachmentRow`.
 */
final readonly class SiteListRow
{
    /**
     * @param  object  $site  bản ghi `sites` gốc
     * @param  int  $rowNo  số thứ tự đã cộng offset phân trang
     * @param  string|null  $kwpText  công suất kWp đã cắt số 0 vô nghĩa; null khi chưa nhập
     * @param  string  $installedAtText  `d/m/Y`; `—` khi trống (gạch DÀI, theo bản cũ)
     * @param  string  $statusBadgeClass  CHUỖI LỚP nền/chữ đầy đủ (không phải tên tông):
     *                                    `bg-{{ … }}` ghép lúc chạy thì Tailwind không sinh ra
     * @param  list<string>  $ownerChips  tên người phụ trách đã tách theo `,` và `;`
     * @param  float  $paidPercent  0–100, view dùng làm BỀ RỘNG thanh tiến độ
     * @param  string  $paidPercentText  cùng số đó đã định dạng để IN (`12,5%`)
     */
    public function __construct(
        public object $site,
        public int $rowNo,
        public ?string $kwpText,
        public ?string $kwText,
        public string $installedAtText,
        public string $warrantyToText,
        public string $statusLabel,
        public string $statusBadgeClass,
        public string $statusIcon,
        public array $ownerChips,
        public string $contractText,
        public string $receivedText,
        public float $paidPercent,
        public string $paidPercentText,
    ) {}
}
