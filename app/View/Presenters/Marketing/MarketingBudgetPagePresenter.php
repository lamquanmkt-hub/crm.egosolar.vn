<?php

declare(strict_types=1);

namespace App\View\Presenters\Marketing;

use App\DTOs\Marketing\BudgetRow;
use App\DTOs\Marketing\BudgetSummaryRow;
use App\DTOs\Marketing\CampaignCombinedRow;
use App\DTOs\Marketing\MetricRow;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị trang `marketing/budget` (Ngân sách & Chỉ số Marketing).
 *
 * Thay bốn khối `@php` của view:
 *  1. `$hasFilter` — năm lượt `request()->filled(...)` quyết định mở sẵn khối "Tổng hợp ngân sách";
 *  2. `$p` — `% tiêu` tính lại cho TỪNG dòng bảng tổng hợp, ngay trong `@forelse`;
 *  3. `$formatBreakdown` — closure định dạng ba cột breakdown của bảng chỉ số;
 *  4. `@php($campaignCombined = $campaignCombined ?? collect())` — phòng hờ thừa: controller
 *     luôn truyền khoá này (xem `MarketingBudgetController::index`).
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 *
 * ⚠️ CỐ Ý KHÔNG dùng `DisplayFormat::money()/number()` cho các cột số. Trang này đang in
 * `number_format($x)` với dấu MẶC ĐỊNH của PHP (`1,234,567` — phẩy ngăn nghìn), còn
 * `DisplayFormat` dùng quy ước Việt Nam (`1.234.567`). Đổi sang là ĐỔI THỨ ĐANG HIỂN THỊ, không
 * còn là refactor. Nên DTO mang SỐ THÔ và view vẫn tự `number_format()` — `number_format` nằm
 * trong nhóm hàm định dạng thuần mà `rules/blade-views.md` cho phép ở Blade. Việc thống nhất
 * quy ước số cho trang này là quyết định riêng của chủ dự án.
 */
final class MarketingBudgetPagePresenter
{
    /**
     * @param  mixed  $rows  paginator các `MarketingBudget` (giữ nguyên để view còn `->links()`)
     * @param  iterable<object>  $summary  dòng gộp theo `month, platform` (SUM budget/actual_spent)
     * @param  iterable<object>  $metricRows  các `MarketingMetric` của khoảng đang lọc
     * @param  iterable<object>  $campaignCombined  dòng gộp budget + metric theo campaign
     * @param  array<string, mixed>  $rawFilters  giá trị THÔ của query string (`from`, `to`,
     *                                            `platform`, `campaign_id`, `month`) — phải là bản
     *                                            thô chứ không phải `$fromView`/`$toView` đã điền
     *                                            mặc định, nếu không `hasFilter` luôn đúng.
     * @return array{rows: mixed, summary: list<BudgetSummaryRow>, metricRows: list<MetricRow>, campaignCombined: list<CampaignCombinedRow>, hasFilter: bool, legacyMonth: string|null}
     */
    public function viewData(
        mixed $rows,
        iterable $summary,
        iterable $metricRows,
        iterable $campaignCombined,
        array $rawFilters,
    ): array {
        return [
            'rows' => $rows->through(fn (object $r): BudgetRow => new BudgetRow(
                id: (int) ($r->id ?? 0),
                monthText: $this->ngay($r->month ?? null, 'm/Y'),
                platform: (string) ($r->platform ?? ''),
                campaignName: (string) ($r->marketingCampaign?->name ?? '-'),
                budget: (float) ($r->budget ?? 0),
                actualSpent: (float) ($r->actual_spent ?? 0),
            )),

            'summary' => $this->dongTongHop($summary),
            'metricRows' => $this->dongChiSo($metricRows),
            'campaignCombined' => $this->dongCampaign($campaignCombined),
            'hasFilter' => $this->coLoc($rawFilters),
            'legacyMonth' => $this->thangCu($rawFilters['month'] ?? null),
        ];
    }

    /** @return list<BudgetSummaryRow> */
    private function dongTongHop(iterable $summary): array
    {
        $out = [];

        foreach ($summary as $s) {
            $budget = (float) ($s->total_budget ?? 0);
            $spent = (float) ($s->total_spent ?? 0);

            $out[] = new BudgetSummaryRow(
                monthText: $this->ngay($s->month ?? null, 'm/Y'),
                platform: (string) ($s->platform ?? ''),
                totalBudget: $budget,
                totalSpent: $spent,
                // Giữ nguyên guard chia-cho-0 của bản cũ: ngân sách 0 thì `% tiêu` là 0, không phải INF.
                percentSpent: $budget > 0 ? round($spent / $budget * 100) : 0.0,
            );
        }

        return $out;
    }

    /** @return list<MetricRow> */
    private function dongChiSo(iterable $metricRows): array
    {
        $out = [];

        foreach ($metricRows as $m) {
            $out[] = new MetricRow(
                id: (int) ($m->id ?? 0),
                dateFromText: $this->ngay($m->date_from ?? null, 'd/m'),
                dateToText: $this->ngay($m->date_to ?? null, 'd/m'),
                platform: (string) ($m->platform ?? ''),
                campaignName: (string) ($m->campaign?->name ?? '-'),
                spend: (float) ($m->spend ?? 0),
                reach: (float) ($m->reach ?? 0),
                leads: (float) ($m->leads ?? 0),
                genderText: $this->breakdown($m->gender_breakdown ?? null),
                ageText: $this->breakdown($m->age_breakdown ?? null),
                regionText: $this->breakdown($m->region_breakdown ?? null),
            );
        }

        return $out;
    }

    /** @return list<CampaignCombinedRow> */
    private function dongCampaign(iterable $campaignCombined): array
    {
        $out = [];

        foreach ($campaignCombined as $c) {
            $monthRaw = (string) ($c->month ?? '');

            $out[] = new CampaignCombinedRow(
                monthText: $this->ngay($monthRaw, 'm/Y'),
                monthRaw: $monthRaw,
                platform: (string) ($c->platform ?? ''),
                campaignName: (string) ($c->campaign_name ?? '-'),
                budget: (float) ($c->budget ?? 0),
                budgetSpent: (float) ($c->budget_spent ?? 0),
                spend: (float) ($c->spend ?? 0),
                reach: (float) ($c->reach ?? 0),
                leads: (float) ($c->leads ?? 0),
            );
        }

        return $out;
    }

    /**
     * Ba cột breakdown: `nam:18, nu:12`.
     *
     * Chép đúng closure cũ, kể cả hai chỗ dễ tưởng là thừa: chỉ lấy cặp có `(int) $v > 0`
     * (nên mảng toàn số 0 vẫn ra `-`), và `number_format($v)` dùng dấu MẶC ĐỊNH của PHP.
     *
     * @param  mixed  $arr  đã được model cast `array`; vẫn nhận null/giá trị lạ vì cột là longtext
     */
    private function breakdown(mixed $arr): string
    {
        if (! $arr || ! is_array($arr) || count($arr) === 0) {
            return '-';
        }

        $pairs = [];
        foreach ($arr as $k => $v) {
            if ((int) $v > 0) {
                $pairs[] = $k.':'.number_format((float) $v);
            }
        }

        return count($pairs) ? implode(', ', $pairs) : '-';
    }

    /**
     * Ngày đã định dạng; **chuỗi rỗng** khi trống.
     *
     * Không gọi `DisplayFormat::date()`: hàm đó trả gạch dài `—` cho giá trị trống, còn cả ba chỗ
     * dùng ở trang này (`optional($r->month)->format()`, `$m->date_from ? … : ''`, `@if($m->date_to)`)
     * đều in RỖNG. Giữ đúng từng ký tự.
     */
    private function ngay(mixed $value, string $format): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * Giá trị THÔ của tham số `month` cũ, hoặc null khi coi như trống.
     *
     * Form lọc nhả lại tham số này dưới dạng input ẩn để link cũ không mất bộ lọc. Bản cũ trong
     * view là `@if(request()->filled('month'))` + `value="{{ request('month') }}"` — tức điều kiện
     * thì TRIM còn giá trị in ra thì KHÔNG. Gộp vào một biến: null nghĩa là không in input, khác
     * null thì in đúng giá trị thô như cũ.
     */
    private function thangCu(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * Có bộ lọc nào đang bật không — quyết định khối "Tổng hợp ngân sách" mở sẵn hay thu lại.
     *
     * Tái hiện `request()->filled($k)`: `filled` = `! blank()`, tức chuỗi chỉ có khoảng trắng
     * vẫn coi là TRỐNG (`vendor/laravel/framework/src/Illuminate/Support/helpers.php`).
     *
     * @param  array<string, mixed>  $rawFilters
     */
    private function coLoc(array $rawFilters): bool
    {
        foreach (['from', 'to', 'platform', 'campaign_id', 'month'] as $key) {
            $v = $rawFilters[$key] ?? null;

            if ($v === null) {
                continue;
            }
            if (is_string($v)) {
                if (trim($v) !== '') {
                    return true;
                }

                continue;
            }
            if (is_array($v) || $v instanceof \Countable) {
                if (count($v) > 0) {
                    return true;
                }

                continue;
            }

            return true;
        }

        return false;
    }
}
