<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một lựa chọn trong ô "Phiếu tạm ứng cần hoàn" của hộp thoại tạo phiếu.
 *
 * Bản cũ nhồi dữ liệu vào `data-*` của từng `<option>` rồi JS đọc lại bằng `option.dataset`.
 * Alpine lấy thẳng mảng này qua `@js(...)` nên không cần `data-*` nữa, nhưng vẫn giữ đủ trường.
 */
final readonly class SettlementAdvanceOption
{
    /**
     * @param  float  $amount  số tiền tạm ứng dạng SỐ — Alpine cần để tính chênh lệch
     * @param  string  $label  chuỗi hiển thị trong `<option>`: `<mã> — <người nhận> — <số tiền> đ`
     * @param  string  $dueText  hạn hoàn ứng `d/m/Y`; `-` khi không có (đúng bản cũ, gạch NGẮN)
     */
    public function __construct(
        public int $id,
        public string $code,
        public float $amount,
        public string $amountText,
        public string $recipient,
        public string $company,
        public string $dueText,
        public string $label,
        public bool $selected,
    ) {}
}
