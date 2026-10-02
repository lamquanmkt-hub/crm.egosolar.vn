<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Giá trị đổ sẵn cho biểu mẫu tài sản (`finance/assets/partials/form-fields`).
 *
 * Tồn tại vì partial đó tự khai hai closure `$val`/`$num` trong khối `@php` và gọi `old()` ngay
 * trong Blade — trong khi partial được include tới N+1 lần trên một trang (một lần cho form thêm
 * mới, một lần cho MỖI dòng tài sản). Nay presenter tính một lượt, view chỉ in.
 *
 * ⚠️ Mọi trường đều là CHUỖI, kể cả id và số tháng: partial cũ so sánh `@selected` bằng `===` sau
 * khi ép `(string)`, nên giữ chuỗi thì HTML và kết quả so sánh không đổi một byte.
 */
final readonly class AssetFormValues
{
    /**
     * @param  string  $originalCost  đã cắt số 0 vô nghĩa theo đúng closure `$num` cũ (`15000000`,
     *                                `1.5`), KHÔNG phải định dạng hiển thị kiểu Việt
     * @param  string  $salvageValue  cùng quy tắc với `$originalCost`
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $categoryId,
        public string $companyId,
        public string $assignedTo,
        public string $department,
        public string $serialNo,
        public string $purchaseDate,
        public string $startUseDate,
        public string $warrantyUntil,
        public string $nextMaintenanceDate,
        public string $originalCost,
        public string $salvageValue,
        public string $usefulLifeMonths,
        public string $depreciationMethod,
        public string $status,
        public string $condition,
        public string $vendor,
        public string $invoiceNo,
        public string $location,
        public string $note,
    ) {}
}
