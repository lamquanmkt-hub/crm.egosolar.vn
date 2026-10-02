<?php

declare(strict_types=1);

/*
| <x-ui.table-wrap> / <x-ui.table> / <x-ui.table-head> — thay .table-responsive / .table /
| .table-sm / .table-hover / thead.table-light.
|
| Ba thứ chốt ở đây đều KHÔNG thể bắt bằng test Feature:
|  1. Hover đổi nền dòng — chỉ tồn tại ở trạng thái :hover, phải có chuột thật.
|  2. Nền xám của đầu bảng đi qua BIẾN CSS (--ego-table-bg). Nếu ai đó đổi <x-ui.table-head>
|     sang đặt nền trực tiếp thì luật ô của bảng (độ đặc hiệu 0,1,1) sẽ thắng luật thead
|     (0,1,0) và đầu bảng mất nền — HTML vẫn đúng, test Feature vẫn xanh.
|  3. main.js::autoWrapTables() bọc mọi bảng chưa có vỏ cuộn. Nếu nó không nhận
|     [data-ego-table-wrap] thì mỗi bảng bị bọc THÊM một div nữa — trang vẫn chạy, chỉ là
|     DOM phình ra và thanh cuộn ngang dính bám nhầm lớp bọc.
|
| Số đo lấy từ bootstrap@5.3.3 trên chính trang này trước khi đổi.
*/

use App\Models\Marketing\MarketingBudget;
use App\Models\Marketing\MarketingCampaign;
use Illuminate\Support\Carbon;

it('bang giu dung dien mao cua bootstrap sau khi doi sang component', function () {
    $thangNay = Carbon::now()->startOfMonth();
    $cp = MarketingCampaign::create(['name' => 'Goodwe 5kw', 'platform' => 'Facebook']);
    MarketingBudget::create([
        'platform' => 'Facebook', 'campaign_id' => $cp->id,
        'month' => $thangNay->toDateString(), 'budget' => 10_000_000, 'actual_spent' => 2_500_000,
    ]);

    $this->actingAs($this->userWithRole('admin', ['name' => 'QTV Bảng', 'email' => 'bang@example.test']));

    $page = visit('/marketing/budget');
    $page->assertNoJavaScriptErrors();

    $css = fn (string $sel, string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).getPropertyValue('.json_encode($prop).')'
    );

    // 1) Bảng: rộng hết, lề dưới 16px, gộp viền.
    expect($css('[data-ego-table="md"]', 'margin-bottom'))->toBe('0px', 'trang này truyền tw:mb-0')
        ->and($css('[data-ego-table="md"]', 'border-collapse'))->toBe('collapse');

    // 2) Ô thân bảng cỡ mặc định: đệm 8px, nền trắng, viền dưới 1px solid #dee2e6.
    //    Trỏ theo `data-ego-table="md"` — bảng ĐẦU TIÊN trong DOM là bản `sm` (đệm 4px).
    $td = '[data-ego-table="md"] > tbody > tr > td';
    expect($css($td, 'padding-top'))->toBe('8px')
        ->and($css($td, 'padding-left'))->toBe('8px')
        ->and($css($td, 'background-color'))->toBe('rgb(255, 255, 255)')
        ->and($css($td, 'border-bottom-width'))->toBe('1px')
        ->and($css($td, 'border-bottom-style'))->toBe('solid')
        ->and($css($td, 'border-bottom-color'))->toBe('rgb(222, 226, 230)');

    // 3) Đầu bảng: nền #f8f9fa qua biến CSS, viền dưới rgb(198,199,200), căn đáy.
    $th = '[data-ego-table="md"] > [data-ego-table-head] > tr > th';
    expect($css($th, 'background-color'))->toBe('rgb(248, 249, 250)',
        'đầu bảng mất nền — <x-ui.table-head> còn gán --ego-table-bg không?')
        ->and($css($th, 'border-bottom-color'))->toBe('rgb(198, 199, 200)')
        ->and($css('[data-ego-table="md"] > [data-ego-table-head]', 'vertical-align'))->toBe('bottom');

    // 4) Bản `sm` của bảng tổng hợp: đệm ô 4px thay vì 8px.
    $page->script("new Promise(r => { window.dispatchEvent(new CustomEvent('toggle-disclosure', { detail: 'budgetSummary' })); requestAnimationFrame(() => requestAnimationFrame(() => r('ok'))); })");
    expect($css('[data-ego-table="sm"] > tbody > tr > td', 'padding-top'))->toBe('4px');

    // 5) main.js KHÔNG được bọc thêm vỏ: số vỏ phải bằng đúng số bảng.
    $soBang = (string) $page->script("document.querySelectorAll('[data-ego-table]').length");
    $soVo = (string) $page->script("document.querySelectorAll('[data-ego-table-wrap], .ego-auto-wrap').length");
    expect($soVo)->toBe($soBang,
        'main.js bọc thêm vỏ — autoWrapTables() chưa nhận [data-ego-table-wrap]?');
});

it('table-hover doi nen dong khi re chuot', function () {
    $thangNay = Carbon::now()->startOfMonth();
    $cp = MarketingCampaign::create(['name' => 'Goodwe 5kw', 'platform' => 'Facebook']);
    MarketingBudget::create([
        'platform' => 'Facebook', 'campaign_id' => $cp->id,
        'month' => $thangNay->toDateString(), 'budget' => 10_000_000, 'actual_spent' => 2_500_000,
    ]);

    $this->actingAs($this->userWithRole('admin', ['name' => 'QTV Hover', 'email' => 'hover@example.test']));

    $page = visit('/marketing/budget');
    $page->assertNoJavaScriptErrors();

    // Playwright ở chế độ strict: hover() đòi selector khớp ĐÚNG MỘT phần tử, mà trang có ba
    // bảng cỡ `md`. Gắn một mốc id cho ô đầu tiên rồi rê chuột vào đúng mốc đó.
    $page->script(<<<'JS'
        (() => {
            const td = document.querySelector('[data-ego-table="md"] > tbody > tr > td');
            td.id = 'egoHoverProbe';
            return 'ok';
        })()
    JS);

    $td = '#egoHoverProbe';
    $bong = fn (): string => (string) $page->script(
        "getComputedStyle(document.querySelector('{$td}')).getPropertyValue('box-shadow')"
    );

    // Bootstrap tô nền dòng bằng box-shadow inset 9999px, mặc định TRONG SUỐT.
    // So nguyên chuỗi chứ không `toContain`: 'rgba(0, 0, 0, 0)' cũng là chuỗi con của
    // 'rgba(0, 0, 0, 0.075)', nên toContain sẽ xanh cả khi hover đang dính sẵn.
    // (⚠️ `toContain` của Pest nhận NHIỀU chuỗi cần chứa, không nhận thông điệp như `toBe`.)
    expect($bong())->toBe('rgba(0, 0, 0, 0) 0px 0px 0px 9999px inset');

    $page->hover($td)->wait(0.3);

    // Mất tw:[&>tbody>tr:hover>*] hoặc quên `pnpm run build` thì dòng dưới đỏ.
    expect($bong())->toBe('rgba(0, 0, 0, 0.075) 0px 0px 0px 9999px inset');
});
