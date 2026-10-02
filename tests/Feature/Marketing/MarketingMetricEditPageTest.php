<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\DTOs\Marketing\MarketingMetricFormValues;
use App\View\Presenters\Marketing\MarketingMetricEditPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guard + presenter trang `marketing/metrics_edit` sau đợt 2026-10-02. */
final class MarketingMetricEditPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/marketing/metrics_edit.blade.php';

    public function test_view_khong_con_php_facade_va_lop_bootstrap(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('Carbon', $view, 'không parse ngày trong Blade');
        $this->assertStringNotContainsString('json_decode', $view);
        $this->assertStringNotContainsString('old(', $view, 'old() đã gộp ở presenter');
        $this->assertStringNotContainsString('<style', $view);

        preg_match_all('/(?<![-:\w])class="([^"]*)"/', $view, $khop);
        $token = [];
        foreach ($khop[1] as $ds) {
            foreach (preg_split('/\s+/', trim($ds)) ?: [] as $l) {
                if ($l !== '' && ! str_starts_with($l, 'tw:') && $l !== 'bi' && ! str_starts_with($l, 'bi-')) {
                    $token[$l] = true;
                }
            }
        }
        $this->assertSame([], array_keys($token), 'view còn lớp không phải `tw:*`');
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));
        preg_match_all('/\$formValues->([a-zA-Z0-9]+)/', $view, $hit);

        $props = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(MarketingMetricFormValues::class))->getProperties()
        );

        $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)));
    }

    public function test_presenter_doc_breakdown_va_ngay(): void
    {
        $v = (new MarketingMetricEditPresenter)->viewData((object) [
            'id' => 5, 'platform' => 'Google', 'campaign_id' => 7,
            'date_from' => Carbon::parse('2026-09-01'), 'date_to' => Carbon::parse('2026-09-30'),
            'reach' => 1000, 'leads' => 20, 'spend' => 500000, 'note' => 'Ghi chú',
            'gender_breakdown' => ['male' => 11, 'female' => 9],
            // Cột lưu CHUỖI JSON (bản cũ có nhánh json_decode) — vẫn phải đọc được.
            'age_breakdown' => json_encode(['25-34' => 4]),
            'region_breakdown' => null,
        ], [])['formValues'];

        $this->assertSame('2026-09-01', $v->dateFrom);
        $this->assertSame('2026-09-30', $v->dateTo);
        $this->assertSame('Google', $v->platform);
        $this->assertSame('7', $v->campaignId);
        $this->assertSame('1000', $v->reach);
        $this->assertSame(11, $v->gender['male']);
        $this->assertSame(0, $v->gender['unknown'], 'khoá thiếu trong breakdown ra 0');
        $this->assertSame(4, $v->age['25-34'], 'đọc được cả khi cột là chuỗi JSON');
        $this->assertSame(0, $v->age['55+']);
        $this->assertSame(0, $v->region['HCM'], 'breakdown null thì mọi khu vực là 0');
    }

    /**
     * ⚠️ Ngữ nghĩa `old()`: khoá CÓ trong old input thì giữ giá trị đó, KỂ CẢ null.
     *
     * Middleware `ConvertEmptyStringsToNull` biến ô bỏ trống thành null; bản cũ
     * (`old('platform', $m->platform)`) in ra RỖNG trong trường hợp này. Dùng `??` để rơi về giá trị
     * DB là SAI — đã đo thấy trang lỗi tự chọn lại `<option selected>` không đúng.
     */
    public function test_old_input_null_khong_roi_ve_gia_tri_trong_db(): void
    {
        $metric = (object) [
            'id' => 5, 'platform' => 'Google', 'campaign_id' => 7, 'date_from' => null, 'date_to' => null,
            'reach' => 1000, 'leads' => 20, 'spend' => 0, 'note' => 'Ghi chú cũ',
            'gender_breakdown' => ['male' => 11], 'age_breakdown' => null, 'region_breakdown' => null,
        ];

        $v = (new MarketingMetricEditPresenter)->viewData($metric, [
            'platform' => null, 'note' => null, 'gender' => ['male' => null],
        ])['formValues'];

        $this->assertSame('', $v->platform, 'old() null → rỗng, KHÔNG phải "Google"');
        $this->assertSame('', $v->note);
        $this->assertSame('', $v->gender['male']);
        // Khoá KHÔNG có trong old input thì mới rơi về giá trị DB.
        $this->assertSame('1000', $v->reach);
        $this->assertSame('7', $v->campaignId);
    }

    public function test_trang_that_in_dung(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 822001, 'name' => 'QT Guard']);
        DB::table('marketing_campaigns')->insert([
            ['id' => 822100, 'name' => 'Chiến dịch Guard', 'platform' => 'Facebook', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('marketing_metrics')->insert([
            ['id' => 822200, 'platform' => 'TikTok', 'campaign_id' => 822100,
                'date_from' => '2026-09-05', 'date_to' => '2026-09-25',
                'reach' => 9999, 'leads' => 77, 'spend' => 123456,
                'gender_breakdown' => json_encode(['male' => 41, 'female' => 36]),
                'age_breakdown' => json_encode(['35-44' => 21]),
                'region_breakdown' => json_encode(['Hà Nội' => 13]),
                'note' => 'Ghi chú Guard', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $html = (string) $this->actingAs($admin)->get('/marketing/metrics/822200/edit')->assertOk()->getContent();

        $this->assertStringContainsString('value="2026-09-05"', $html);
        $this->assertStringContainsString('value="2026-09-25"', $html);
        $this->assertStringContainsString('value="9999"', $html);
        $this->assertStringContainsString('value="41"', $html);
        $this->assertStringContainsString('value="21"', $html);
        $this->assertStringContainsString('value="13"', $html);
        $this->assertStringContainsString('Ghi chú Guard', $html);
        $this->assertStringContainsString('Chiến dịch Guard', $html);
        // Kênh TikTok và chiến dịch đều được chọn lại.
        $this->assertSame(2, substr_count($html, 'selected'));
    }
}
