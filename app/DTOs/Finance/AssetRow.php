<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng bảng tài sản của `finance/assets`.
 *
 * Gom mọi giá trị mà view cũ tự tính bằng closure trong `@php` (tiền, ngày, ghép chuỗi người giữ)
 * và luôn mang theo `form` — bộ giá trị cho biểu mẫu sửa nằm trong hàng chi tiết của chính dòng đó.
 */
final readonly class AssetRow
{
    /**
     * @param  int  $stt  số thứ tự đã cộng offset trang, đúng công thức `$loop->iteration + (trang-1)*mỗiTrang`
     * @param  string  $assignedText  người giữ + bộ phận đã ghép: `Nguyễn A · Kỹ thuật`,
     *                                `Chưa bàn giao` khi trống
     * @param  string  $progressPercentText  bề rộng thanh tiến độ, in thẳng vào `width:…%`
     * @param  string  $statusBadgeClass  CHUỖI LỚP nền/chữ đầy đủ của huy hiệu trạng thái; rỗng khi
     *                                    trạng thái không nằm trong bảng màu (huy hiệu chỉ còn hình dạng)
     * @param  list<AssetEventRow>  $events
     * @param  list<AssetFileRow>  $files
     */
    public function __construct(
        public int $id,
        public int $stt,
        public string $code,
        public string $name,
        public string $serialText,
        public string $locationText,
        public string $categoryName,
        public string $categoryColor,
        public string $companyName,
        public string $assignedText,
        public string $costText,
        public string $bookValueText,
        public string $accumulatedText,
        public string $monthlyText,
        public string $progressPercentText,
        public string $usageText,
        public string $statusBadgeClass,
        public string $statusLabel,
        public string $conditionLabel,
        public string $nextMaintenanceText,
        public string $warrantyText,
        public array $events,
        public array $files,
        public AssetFormValues $form,
    ) {}
}
