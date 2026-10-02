<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Tiến độ hồ sơ tạm ứng: một ô bảng gộp nhãn + tông màu + icon + dòng chú thích nhỏ.
 *
 * Tồn tại vì view khai closure `$stageFor` dài 32 dòng ngay trong `@php`, và closure đó gọi
 * `Carbon::parse()` + `now()` để tính số ngày quá hạn — tức tính toán nghiệp vụ nằm trong Blade.
 */
final readonly class AdvanceStage
{
    /**
     * @param  string  $toneClass  CHUỖI LỚP nền/chữ/viền đầy đủ, quy đổi từ `.ego-fin-stage--<tông>`
     *                             của khối `<style>` cũ. Chuỗi phải TĨNH trong mã nguồn thì Tailwind
     *                             mới quét thấy; ghép `tw:bg-[…]` lúc chạy là lớp không được sinh ra.
     * @param  string  $icon  tên icon Bootstrap Icons (`bi-…`), không kèm tiền tố `bi`
     * @param  bool  $overdue  true khi nhãn là "Quá hạn hoàn ứng N ngày" — cột hạn hoàn ứng tô đỏ
     *                         theo đúng điều kiện cũ (`tone === 'danger'` VÀ nhãn chứa "Quá hạn")
     */
    public function __construct(
        public string $label,
        public string $toneClass,
        public string $icon,
        public string $note,
        public bool $overdue = false,
    ) {}
}
