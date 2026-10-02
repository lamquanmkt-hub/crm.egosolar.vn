<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\FinanceOrderProfitRow;
use App\Support\DisplayFormat;
use Carbon\Carbon;

/**
 * Định dạng cho `finance/index` (dashboard tài chính).
 *
 * Thay khối `@php` khai một closure `$money` rồi gọi 14 chỗ, cộng 4 lượt `number_format` và
 * một lượt `Carbon::parse` viết thẳng trong view.
 *
 * Thuần: không Facade, không truy vấn, không đọc request — mọi số do controller đưa vào.
 */
final class FinanceDashboardPresenter
{
    /** Lớp màu theo DẤU của số. Giữ nguyên tên lớp của bản cũ để HTML không đổi. */
    private const TONE_DUONG = 'tw:text-[#166534]';

    private const TONE_AM = 'tw:text-[#dc2626]';

    /**
     * @param  array<string, mixed>  $so  các chỉ số thô từ controller
     * @param  iterable<int, object>  $donGanDay  dòng thô của bảng lợi nhuận đơn hàng
     * @return array<string, mixed>
     */
    public function viewData(array $so, iterable $donGanDay): array
    {
        $loiNhuan = (float) ($so['grossProfit'] ?? 0);
        $dongTienRong = (float) ($so['netCashFlow'] ?? 0);

        $rows = [];
        foreach ($donGanDay as $dong) {
            $rows[] = $this->dongDon($dong);
        }

        return [
            // Hero
            'collectionRateText' => DisplayFormat::percent($so['collectionRate'] ?? 0, 1),
            'totalOrdersText' => DisplayFormat::number($so['totalOrders'] ?? 0),
            'pendingRequestsText' => DisplayFormat::number($so['pendingPaymentRequests'] ?? 0),

            // Doanh thu — giá vốn — lợi nhuận
            'revenueText' => DisplayFormat::money($so['totalRevenue'] ?? 0),
            'costText' => DisplayFormat::money($so['totalCost'] ?? 0),
            'grossProfitText' => DisplayFormat::money($loiNhuan),
            'grossProfitClass' => $this->tone($loiNhuan),
            'grossMarginText' => DisplayFormat::percent($so['grossMargin'] ?? 0, 2),

            // Công nợ
            'receivableBaseText' => DisplayFormat::money($so['totalReceivableBase'] ?? 0),
            'collectedText' => DisplayFormat::money($so['collectedAmount'] ?? 0),
            'receivableText' => DisplayFormat::money($so['receivableAmount'] ?? 0),
            'overdueText' => DisplayFormat::money($so['overdueReceivables'] ?? 0),

            // Dòng tiền
            'cashInText' => DisplayFormat::money($so['cashInPeriod'] ?? 0),
            'cashOutText' => DisplayFormat::money($so['cashOutPeriod'] ?? 0),
            'netCashFlowText' => DisplayFormat::money($dongTienRong),
            'netCashFlowClass' => $this->tone($dongTienRong),
            'netCashFlowBadgeClass' => $dongTienRong >= 0 ? 'tw:bg-[rgba(22,163,74,0.10)] tw:text-[#166534] tw:border-[rgba(22,163,74,0.16)]' : 'tw:bg-[rgba(220,38,38,0.10)] tw:text-[#dc2626] tw:border-[rgba(220,38,38,0.16)]',
            'netCashFlowBadgeText' => $dongTienRong >= 0 ? 'Dương' : 'Âm',
            'pendingDisbursementText' => DisplayFormat::money($so['pendingDisbursement'] ?? 0),

            'profitRows' => $rows,
        ];
    }

    private function tone(float $giaTri): string
    {
        return $giaTri >= 0 ? self::TONE_DUONG : self::TONE_AM;
    }

    private function dongDon(object $dong): FinanceOrderProfitRow
    {
        $loi = (float) ($dong->gross_profit ?? 0);
        $bien = (float) ($dong->margin_percent ?? 0);

        return new FinanceOrderProfitRow(
            codeText: $this->maDon($dong),
            dateText: $this->ngay($dong->order_date ?? null),
            saleText: DisplayFormat::money($dong->sale_amount ?? 0),
            costText: DisplayFormat::money($dong->cost_amount ?? 0),
            profitText: DisplayFormat::money($loi),
            profitClass: $this->tone($loi),
            marginText: DisplayFormat::percent($bien, 2),
            marginClass: $bien >= 0 ? 'tw:bg-[rgba(22,163,74,0.10)] tw:text-[#166534]' : 'tw:bg-[rgba(220,38,38,0.10)] tw:text-[#dc2626]',
        );
    }

    /**
     * Bản cũ viết `$row->order_code ?? ('#' . ($row->order_id ?? ''))`.
     *
     * `??` chỉ bắt NULL, nên mã đơn là chuỗi RỖNG thì vẫn in ra rỗng — KHÔNG rơi về `#<id>`.
     * Giữ đúng ngữ nghĩa đó, đừng "sửa" thành `?:` cho gọn.
     */
    private function maDon(object $dong): string
    {
        $ma = $dong->order_code ?? null;

        if ($ma !== null) {
            return (string) $ma;
        }

        return '#'.($dong->order_id ?? '');
    }

    /**
     * Bản cũ: `!empty($order_date) ? Carbon::parse(...)->format('d/m/Y') : '--'`.
     *
     * Không dùng được `DisplayFormat::date()` — hàm đó trả `—` (gạch dài) khi trống, còn trang
     * này in `--` (hai gạch ngắn). Một ký tự khác là HTML khác.
     */
    private function ngay(mixed $giaTri): string
    {
        if (empty($giaTri)) {
            return '--';
        }

        return Carbon::parse($giaTri)->format('d/m/Y');
    }
}
