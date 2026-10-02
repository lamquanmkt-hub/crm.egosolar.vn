<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Đọc MỘT quy tắc hoa hồng: nó thuộc loại gì, có áp cho đơn này không, và ra
 * bao nhiêu tiền.
 *
 * ## Vì sao gom về đây
 * Phần này từng tồn tại hai bản gần như chép nguyên văn — một trong
 * {@see CommissionCalculator} (màn hình), một trong
 * `SalesCommissionExcelExporter` — kể cả cùng danh sách chuỗi ma thuật và cùng
 * dung sai 0,01. Hai bản chép tay của cùng một quy tắc tính tiền là chuyện chỉ
 * chờ ngày lệch nhau.
 *
 * Lớp này CỐ TÌNH không biết gì về đơn hàng hay bộ quy tắc tổng thể: nó chỉ trả
 * lời về một quy tắc đơn lẻ. Việc chọn quy tắc nào thắng vẫn thuộc về bên gọi.
 *
 * ## Vì sao phải chuẩn hoá chuỗi
 * Dữ liệu cấu hình do người dùng gõ: `trade_product`, `Trade Product`,
 * `thương mại` đều xuất hiện thật. Nên mọi so khớp đều đi qua {@see normalize()}
 * — bỏ dấu, bỏ ký tự không phải chữ/số, về chữ thường.
 */
final class CommissionRuleMatcher
{
    private const TRADE_TYPES = ['tradeproduct', 'thuongmai', 'commercial', 'sales', 'order'];

    private const SOLAR_PANEL_TYPES = ['solarpanel', 'tampin', 'panel'];

    private const STATUS_TARGETS = ['customerstatus', 'khachhang', 'trangthaikhach'];

    private const KEYWORD_TARGETS = ['keyword', 'product', 'productname', 'model', 'sku', 'barcode', 'brand', 'category', 'sanpham'];

    /** Khách lẻ được gọi bằng nhiều tên khác nhau trong cấu hình. */
    private const RETAIL_WORDS = ['khachle', 'khachhangle', 'retail', 'retailcustomer', 'le'];

    private const AFTER_VAT_BASES = ['revenueaftervat', 'aftervat', 'sauvat'];

    private const QUANTITY_BASES = ['quantity', 'qty', 'soluong'];

    private const PER_ITEM_CALCS = ['fixedperitem', 'peritem', 'theosanpham'];

    private const PER_KWP_CALCS = ['fixedperkwp', 'perkwp'];

    private const PER_ORDER_CALCS = ['fixedperorder', 'perorder', 'fixed'];

    /** Sai số khi so mốc doanh thu và mốc số lượng. */
    private const RANGE_TOLERANCE = 0.01;

    /**
     * Quy tắc đang bật, quy tắc ưu tiên cao đứng trước.
     *
     * @param  Collection<int, mixed>  $rules
     * @return Collection<int, object>
     */
    public function activeRules(Collection $rules): Collection
    {
        return $rules
            ->filter(fn ($rule) => (int) ($rule->is_active ?? 0) === 1)
            ->sortByDesc(fn ($rule) => (int) ($rule->priority ?? 0))
            ->values();
    }

    public function isTrade(object $rule): bool
    {
        return in_array($this->type($rule, 'trade_product'), self::TRADE_TYPES, true);
    }

    public function isSolarPanel(object $rule): bool
    {
        return in_array($this->type($rule, ''), self::SOLAR_PANEL_TYPES, true);
    }

    /** Quy tắc có áp cho đơn mang nhóm khách và mặt hàng này không. */
    public function matchesTarget(object $rule, ?string $customerStatus, ?string $productText): bool
    {
        $targetType = $this->normType($rule->target_type ?? 'all');
        $needles = $this->targetNeedles($rule->target_text ?? '');

        if ($targetType === '' || $targetType === 'all' || $needles === []) {
            return true;
        }

        if (in_array($targetType, self::STATUS_TARGETS, true)) {
            return $this->matchesStatus($needles, $this->normalize($customerStatus));
        }

        if (in_array($targetType, self::KEYWORD_TARGETS, true)) {
            return $this->matchesKeyword($needles, $this->normalize($productText));
        }

        // Loại đích lạ thì không loại trừ đơn nào — thà tính thừa hơn bỏ sót.
        return true;
    }

    /**
     * Số tiền quy tắc này sinh ra cho đơn; `null` nghĩa là đơn nằm ngoài khoảng
     * áp dụng của quy tắc (mốc doanh thu tháng hoặc mốc số lượng).
     */
    public function amountFor(
        object $rule,
        float $beforeVat,
        float $afterVat,
        float $monthlyBeforeVat,
        float $quantity,
    ): ?float {
        if (! $this->inRange($rule->from_amount ?? null, $rule->to_amount ?? null, $monthlyBeforeVat)) {
            return null;
        }

        if (! $this->inRange($rule->from_qty ?? null, $rule->to_qty ?? null, $quantity)) {
            return null;
        }

        $calcType = $this->normType($rule->calculation_type ?? 'percent');

        if (in_array($calcType, self::PER_ITEM_CALCS, true)) {
            return $quantity * (float) ($rule->amount_per_unit ?? ($rule->fixed_amount ?? 0));
        }

        if (in_array($calcType, self::PER_KWP_CALCS, true)) {
            return $quantity * (float) ($rule->amount_per_kwp ?? 0);
        }

        if (in_array($calcType, self::PER_ORDER_CALCS, true)) {
            return (float) ($rule->fixed_amount ?? 0);
        }

        return $this->base($rule, $beforeVat, $afterVat, $quantity) * (float) ($rule->rate_percent ?? 0) / 100;
    }

    /** Bỏ dấu, bỏ ký tự không phải chữ/số, về chữ thường. */
    public function normalize(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if (class_exists(Str::class)) {
            $value = Str::ascii($value);
        }

        return preg_replace('/[^a-z0-9]+/u', '', strtolower($value)) ?: '';
    }

    private function type(object $rule, string $default): string
    {
        return $this->normType($rule->commission_type ?? $default);
    }

    private function normType(mixed $value): string
    {
        return $this->normalize(str_replace(['_', '-'], '', (string) $value));
    }

    /** @return list<string> */
    private function targetNeedles(mixed $text): array
    {
        return collect(preg_split('/[,;|\/]+/', (string) $text) ?: [])
            ->map(fn ($part) => $this->normalize($part))
            ->filter()
            ->values()
            ->all();
    }

    /** @param list<string> $needles */
    private function matchesStatus(array $needles, string $status): bool
    {
        if ($status === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if ($needle === $status) {
                return true;
            }

            // "lead" và "ads" là cùng một nhóm khách trong cách gọi nội bộ.
            if ($this->isLeadOrAds($needle) && $this->isLeadOrAds($status)) {
                return true;
            }

            if (in_array($needle, self::RETAIL_WORDS, true) && in_array($status, self::RETAIL_WORDS, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $needles */
    private function matchesKeyword(array $needles, string $productText): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($productText, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isLeadOrAds(string $value): bool
    {
        return str_contains($value, 'lead') || str_contains($value, 'ads');
    }

    /** Ngưỡng rỗng nghĩa là không giới hạn phía đó. */
    private function inRange(mixed $from, mixed $to, float $value): bool
    {
        $from = ($from ?? '') !== '' && $from !== null ? (float) $from : null;
        $to = ($to ?? '') !== '' && $to !== null ? (float) $to : null;

        if ($from !== null && $value + self::RANGE_TOLERANCE < $from) {
            return false;
        }

        return $to === null || $value - self::RANGE_TOLERANCE <= $to;
    }

    private function base(object $rule, float $beforeVat, float $afterVat, float $quantity): float
    {
        $baseType = $this->normType($rule->base_type ?? 'revenue_before_vat');

        if (in_array($baseType, self::AFTER_VAT_BASES, true)) {
            return $afterVat;
        }

        return in_array($baseType, self::QUANTITY_BASES, true) ? $quantity : $beforeVat;
    }
}
