<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\AssetEventRow;
use App\DTOs\Finance\AssetFileRow;
use App\DTOs\Finance\AssetFormValues;
use App\DTOs\Finance\AssetRow;
use App\View\Presenters\Finance\AssetListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard trang `finance/assets` sau đợt 2026-09-30: bỏ `@php`, bỏ `<style>`, bỏ `<script>`.
 *
 * Guard hai chiều — view chỉ in, và trang thật in đúng giá trị.
 */
final class AssetListPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/finance/assets/index.blade.php';

    private const PARTIAL = 'views/finance/assets/partials/form-fields.blade.php';

    /** Khoá view() do controller cấp (không qua presenter). */
    private const CONTROLLER_KEYS = [
        'assets', 'summary', 'filters', 'categories', 'companies', 'users',
        'statuses', 'conditions', 'eventTypes',
    ];

    /** Biến vòng lặp và biến Blade tự cấp. */
    private const LOOP_AND_BLADE_VARIABLES = [
        'row', 'event', 'file', 'c', 'u', 'k', 'v', 'form',
        'loop', 'errors', 'slot', 'attributes', 'component',
    ];

    /**
     * Magic của Alpine — JS trong thuộc tính `x-*`, KHÔNG phải biến Blade. Chúng cũng bắt đầu
     * bằng `$` nên regex quét biến vớt phải; liệt kê tường minh thay vì nới regex.
     */
    private const ALPINE_MAGICS = ['el', 'refs', 'nextTick', 'dispatch', 'event', 'data', 'store'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));
        $partial = (string) file_get_contents(resource_path(self::PARTIAL));

        foreach (['index' => $view, 'partial form-fields' => $partial] as $ten => $nguon) {
            $this->assertStringNotContainsString('@php', $nguon, "{$ten} còn khối @php");
            $this->assertStringNotContainsString('Carbon', $nguon, "{$ten} còn tự parse ngày");
            $this->assertStringNotContainsString('number_format', $nguon, "{$ten} còn tự định dạng số");
            $this->assertStringNotContainsString('old(', $nguon, "{$ten} còn tự đọc old input");
        }

        // Trang đã chuyển XONG cả ba nửa: không CSS riêng, không JS thuần, không Bootstrap.
        $this->assertStringNotContainsString('<style', $view, 'view dùng lại khối <style>');
        $this->assertStringNotContainsString('<script', $view, 'view dùng lại JS thuần');
        $this->assertStringNotContainsString('onclick=', $view, 'view dùng lại handler nội tuyến');
        $this->assertStringNotContainsString('data-bs-', $view, 'view dùng lại Bootstrap JS');
        $this->assertStringNotContainsString('class="ap-', $view, 'view dùng lại hệ class ap-* cũ');

        // Không có dấu `!` nào — mọi tranh chấp đã xử bằng cách bỏ thuộc tính khỏi lớp nền.
        $this->assertKhongLamDungImportant($view, 'finance/assets');

        $data = (new AssetListPresenter)->viewData(
            assets: [], statuses: [], conditions: [], eventTypes: [],
            oldInput: [], sttOffset: 0, summary: [],
        );

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $view.$partial, $m);
        $provided = array_merge(
            self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, self::ALPINE_MAGICS, array_keys($data)
        );

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $nguon = (string) file_get_contents(resource_path(self::VIEW))
            .(string) file_get_contents(resource_path(self::PARTIAL));

        $canh = [
            'row' => AssetRow::class,
            'event' => AssetEventRow::class,
            'file' => AssetFileRow::class,
            'form' => AssetFormValues::class,
        ];

        foreach ($canh as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $nguon, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    public function test_moi_nut_alpine_goi_dung_ten_bien_ma_khoi_dang_nghe(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        // Sai tên biến thì bấm KHÔNG ra gì và cũng không báo lỗi — chốt bằng đếm.
        $this->assertStringContainsString('x-data="{ moThem: false }"', $view);
        $this->assertStringContainsString('x-show="moThem"', $view);
        $this->assertStringContainsString('x-data="{ moChiTiet: {} }"', $view);
        $this->assertStringContainsString('x-show="moChiTiet[{{ $row->id }}]"', $view);

        // Hàng chi tiết dựa vào `[x-cloak]{display:none!important}` của resources/css/app.css;
        // thiếu nó thì mọi hàng chi tiết HIỆN một nhịp lúc tải trang.
        $this->assertSame(2, substr_count($view, 'x-cloak'), 'thiếu x-cloak ở khối dùng x-show');
        $this->assertStringContainsString('[x-cloak]', (string) file_get_contents(resource_path('css/app.css')));
    }

    public function test_trang_that_in_dung_gia_tri_va_co_nhan_tro_nang(): void
    {
        Carbon::setTestNow('2026-09-30 09:15:00');

        DB::table('finance_asset_categories')->insert([
            'id' => 880001, 'code' => 'GUARD_A', 'name' => 'Nhóm Guard',
            'useful_life_months' => 60, 'color' => '#2563eb',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('finance_assets')->insert([
            'id' => 880001, 'code' => 'TS-GUARD', 'name' => 'Máy hàn Guard',
            'category_id' => 880001, 'company_id' => null, 'assigned_to' => null,
            'department' => null, 'serial_no' => 'SN-GUARD', 'purchase_date' => '2025-03-15',
            'start_use_date' => '2025-04-01', 'warranty_until' => null,
            'next_maintenance_date' => '2026-10-10', 'vendor' => null, 'invoice_no' => null,
            'original_cost' => 150000000, 'salvage_value' => 5000000, 'useful_life_months' => 60,
            'depreciation_method' => 'straight_line', 'location' => 'Kho A',
            'status' => 'active', 'condition' => 'good', 'note' => null,
            'created_by' => null, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null,
        ]);

        $html = (string) $this->actingAs($this->userWithRole('accounting', [], ['page.finance']))
            ->get('/finance/assets')->assertOk()->getContent();

        $this->assertStringContainsString('TS-GUARD', $html);
        $this->assertStringContainsString('150.000.000 đ', $html, 'nguyên giá kiểu Việt');
        $this->assertStringContainsString('Chưa bàn giao', $html);
        $this->assertStringContainsString('Nhóm Guard', $html);
        $this->assertStringContainsString('10/10/2026', $html, 'ngày bảo trì d/m/Y');
        $this->assertStringContainsString('tw:bg-[#dcfce7]', $html, 'huy hiệu trạng thái đang dùng');

        // Tiếp cận: nút biểu tượng phải có nhãn chữ, thanh tiến độ phải có ngữ nghĩa,
        // ô tiêu đề cột phải khai scope. Ba thứ này bản cũ đều thiếu.
        $this->assertStringContainsString('aria-label="Xem chi tiết tài sản TS-GUARD"', $html);
        $this->assertStringContainsString('aria-label="Sửa tài sản TS-GUARD"', $html);
        $this->assertStringContainsString('aria-label="Xóa tài sản TS-GUARD"', $html);
        $this->assertStringContainsString('aria-controls="asset-detail-880001"', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('scope="col"', $html);
    }

    public function test_danh_sach_rong_van_in_dong_thong_bao(): void
    {
        $html = (string) $this->actingAs($this->userWithRole('accounting', [], ['page.finance']))
            ->get('/finance/assets?keyword=khong-bao-gio-co-ma-nay')->assertOk()->getContent();

        $this->assertStringContainsString('Chưa có tài sản nào.', $html);
    }
}
