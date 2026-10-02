<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\View\Presenters\Marketing\MarketingBudgetPagePresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Presenter trang ngân sách marketing — dựng dữ liệu trong bộ nhớ, assert GIÁ TRỊ THẬT.
 *
 * Kế thừa `PHPUnit\Framework\TestCase` (không boot app): lớp này thuần nên không cần container.
 * stdClass phải mang ĐỦ thuộc tính như model thật — stdClass ném lỗi khi thiếu, Eloquent thì trả null.
 */
final class MarketingBudgetPagePresenterTest extends TestCase
{
    private function presenter(): MarketingBudgetPagePresenter
    {
        return new MarketingBudgetPagePresenter;
    }

    /**
     * Paginator rỗng/có sẵn item, LUÔN truyền `currentPage` tường minh.
     *
     * Bỏ trống tham số đó thì paginator gọi `Paginator::resolveCurrentPage()`, mà resolver này do
     * Laravel cài lúc boot và nó đọc `$app['request']`. Lớp test này cố ý KHÔNG boot app, nên khi
     * chạy cả bộ (một test Feature khác đã cài resolver rồi tháo app) sẽ đỏ
     * "Target class [request] does not exist" — chạy riêng một tệp thì không lộ ra.
     *
     * @param  list<object>  $items
     */
    private function paginator(array $items = []): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, count($items), 20, 1);
    }

    public function test_phan_tram_tieu_lam_tron_va_khong_chia_cho_0(): void
    {
        $data = $this->presenter()->viewData(
            rows: $this->paginator(),
            summary: [
                (object) ['month' => '2026-09-01', 'platform' => 'Facebook', 'total_budget' => 10_000_000, 'total_spent' => 2_500_000],
                (object) ['month' => '2026-08-01', 'platform' => 'Zalo', 'total_budget' => 0, 'total_spent' => 0],
                (object) ['month' => '2026-07-01', 'platform' => 'Google', 'total_budget' => 3, 'total_spent' => 2],
            ],
            metricRows: [],
            campaignCombined: [],
            rawFilters: [],
        );

        $this->assertSame('09/2026', $data['summary'][0]->monthText);
        $this->assertSame(25.0, round($data['summary'][0]->percentSpent, 6));

        // Ngân sách 0 -> 0%, KHÔNG phải INF/NaN (guard chia-cho-0 của bản cũ).
        $this->assertSame(0.0, round($data['summary'][1]->percentSpent, 6));

        // 2/3 = 66,67% -> round() ra 67, đúng như `round(...)` không đối số thứ hai của bản cũ.
        $this->assertSame(67.0, round($data['summary'][2]->percentSpent, 6));
    }

    /**
     * Ba cột breakdown: chỉ lấy cặp có giá trị > 0; mọi trường hợp "không có gì để in" đều ra `-`.
     *
     * @param  mixed  $input  giá trị cột longtext sau khi model cast `array`
     */
    #[DataProvider('breakdowns')]
    public function test_breakdown_dinh_dang_dung(mixed $input, string $mong): void
    {
        $data = $this->presenter()->viewData(
            rows: $this->paginator(),
            summary: [],
            metricRows: [$this->metric(['gender_breakdown' => $input])],
            campaignCombined: [],
            rawFilters: [],
        );

        $this->assertSame($mong, $data['metricRows'][0]->genderText);
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function breakdowns(): array
    {
        return [
            'null' => [null, '-'],
            'mảng rỗng' => [[], '-'],
            'toàn số 0' => [['HCM' => 0, 'HN' => 0], '-'],
            'số âm' => [['HCM' => -5], '-'],
            'chuỗi không phải mảng' => ['rác', '-'],
            'một cặp' => [['nam' => 4], 'nam:4'],
            'nhiều cặp giữ thứ tự' => [['nam' => 18, 'nu' => 12], 'nam:18, nu:12'],
            'bỏ cặp bằng 0, giữ cặp dương' => [['nam' => 0, 'nu' => 7], 'nu:7'],
            'ngăn nghìn dấu phẩy như bản cũ' => [['HCM' => 12345], 'HCM:12,345'],
        ];
    }

    public function test_ngay_trong_thi_ra_chuoi_rong_chu_khong_phai_gach_dai(): void
    {
        $data = $this->presenter()->viewData(
            rows: $this->paginator([
                (object) ['id' => 7, 'month' => null, 'platform' => 'Zalo', 'marketingCampaign' => null, 'budget' => 0, 'actual_spent' => 0],
            ]),
            summary: [],
            metricRows: [$this->metric(['date_from' => '2026-09-03', 'date_to' => null])],
            campaignCombined: [],
            rawFilters: [],
        );

        $row = $data['rows']->items()[0];
        $this->assertSame('', $row->monthText, 'optional($r->month)->format() cũ in RỖNG, không phải —');
        $this->assertSame('-', $row->campaignName, 'không có quan hệ thì in gạch ngang');

        $this->assertSame('03/09', $data['metricRows'][0]->dateFromText);
        $this->assertSame('', $data['metricRows'][0]->dateToText, 'date_to null -> rỗng, view mới không in dấu -');
    }

    /** @param array<string, mixed> $filters */
    #[DataProvider('boLoc')]
    public function test_has_filter_theo_dung_nghia_filled(array $filters, bool $mong): void
    {
        $data = $this->presenter()->viewData(
            rows: $this->paginator(),
            summary: [], metricRows: [], campaignCombined: [],
            rawFilters: $filters,
        );

        $this->assertSame($mong, $data['hasFilter']);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: bool}> */
    public static function boLoc(): array
    {
        return [
            'rỗng hoàn toàn' => [[], false],
            'toàn null' => [['from' => null, 'to' => null, 'platform' => null, 'campaign_id' => null, 'month' => null], false],
            'chuỗi rỗng' => [['platform' => ''], false],
            'chỉ khoảng trắng (filled() trim)' => [['platform' => '   '], false],
            'có kênh' => [['platform' => 'Facebook'], true],
            'có from' => [['from' => '01/09/2026'], true],
            'month legacy' => [['month' => '2026-09'], true],
            'campaign_id là số 0 dạng chuỗi' => [['campaign_id' => '0'], true],
            'khoá lạ không tính' => [['keyword' => 'abc'], false],
        ];
    }

    public function test_month_legacy_giu_gia_tri_tho_va_null_khi_trong(): void
    {
        $goi = fn (mixed $month): mixed => $this->presenter()->viewData(
            rows: $this->paginator(),
            summary: [], metricRows: [], campaignCombined: [],
            rawFilters: ['month' => $month],
        )['legacyMonth'];

        $this->assertNull($goi(null));
        $this->assertNull($goi(''));
        $this->assertNull($goi('  '), 'điều kiện in input thì TRIM như filled()');
        $this->assertSame('2026-09', $goi('2026-09'), 'giá trị in ra thì giữ nguyên bản thô');
    }

    public function test_campaign_tong_hop_giu_hai_dang_thang(): void
    {
        $data = $this->presenter()->viewData(
            rows: $this->paginator(),
            summary: [], metricRows: [],
            campaignCombined: [
                (object) ['month' => '2026-09-01', 'platform' => 'Facebook', 'campaign_id' => 1, 'campaign_name' => 'Goodwe', 'budget' => 5.0, 'budget_spent' => 1.0, 'spend' => 2.0, 'reach' => 3.0, 'leads' => 4.0],
                (object) ['month' => '01/08/2026 - 30/09/2026', 'platform' => 'Google', 'campaign_id' => null, 'campaign_name' => null, 'budget' => 0, 'budget_spent' => 0, 'spend' => 9.0, 'reach' => 8.0, 'leads' => 7.0],
            ],
            rawFilters: [],
        );

        $this->assertSame('09/2026', $data['campaignCombined'][0]->monthText, 'lọc trong một tháng -> m/Y');
        $this->assertSame('Goodwe', $data['campaignCombined'][0]->campaignName);

        // Nhãn khoảng không parse được thành ngày -> giữ nguyên để view in thẳng qua monthRaw.
        $this->assertSame('01/08/2026 - 30/09/2026', $data['campaignCombined'][1]->monthRaw);
        $this->assertSame('-', $data['campaignCombined'][1]->campaignName, 'không có tên -> gạch ngang');
    }

    /**
     * Một `MarketingMetric` giả với ĐỦ thuộc tính view/presenter đọc tới.
     *
     * @param  array<string, mixed>  $ghiDe
     */
    private function metric(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'date_from' => '2026-09-03',
            'date_to' => '2026-09-10',
            'platform' => 'Facebook',
            'campaign' => null,
            'spend' => 0,
            'reach' => 0,
            'leads' => 0,
            'gender_breakdown' => null,
            'age_breakdown' => null,
            'region_breakdown' => null,
        ], $ghiDe);
    }
}
