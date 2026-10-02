<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nguồn duy nhất cho id công ty vận hành mặc định (EGO Việt Nam).
 *
 * Toàn bộ module Kho/Sản phẩm chỉ phục vụ một công ty. Trước đây mỗi
 * controller tự khai `private const EGO_VN_COMPANY_ID = 1` — 4 bản sao của
 * cùng một hằng nghiệp vụ, không nơi nào test được vì không đổi được giá trị.
 *
 * @see config/ego.php Giá trị cấu hình và biến môi trường ghi đè.
 */
final class EgoCompanyContext
{
    /** Giá trị dùng khi config chưa được nạp (an toàn cho lệnh console sớm). */
    private const FALLBACK_COMPANY_ID = 1;

    /**
     * Id công ty vận hành mặc định.
     *
     * KHÔNG cache tĩnh: test cần đổi config giữa các case, và chi phí đọc
     * config đã nằm sẵn trong bộ nhớ là không đáng kể.
     */
    public static function defaultCompanyId(): int
    {
        $configured = (int) config('ego.default_company_id', self::FALLBACK_COMPANY_ID);

        return $configured > 0 ? $configured : self::FALLBACK_COMPANY_ID;
    }
}
