<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng trong bảng "Lợi nhuận đơn hàng gần đây" của `finance/index`.
 *
 * Tồn tại vì view tự định dạng tiền, tự parse ngày và tự suy lớp màu theo DẤU của lợi nhuận
 * cho từng dòng, mỗi lần dựng trang.
 */
final readonly class FinanceOrderProfitRow
{
    /**
     * @param  string  $codeText  mã đơn, hoặc `#<id>` khi mã là NULL (xem ghi chú ở presenter)
     * @param  string  $dateText  `d/m/Y`; `--` (HAI gạch) khi không có ngày — không phải `—`
     * @param  string  $profitClass  `text-success` khi ≥ 0, `text-danger` khi âm
     * @param  string  $marginClass  `finance-margin--good` / `finance-margin--bad`
     */
    public function __construct(
        public string $codeText,
        public string $dateText,
        public string $saleText,
        public string $costText,
        public string $profitText,
        public string $profitClass,
        public string $marginText,
        public string $marginClass,
    ) {}
}
