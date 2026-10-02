<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\View\Presenters\Projects\SiteListPresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

/**
 * Presenter danh sách công trình — dựng dữ liệu trong bộ nhớ, assert GIÁ TRỊ THẬT.
 *
 * Kế thừa `PHPUnit\Framework\TestCase` (không boot app) vì lớp này thuần.
 */
final class SiteListPresenterTest extends TestCase
{
    /**
     * Paginator LUÔN truyền `currentPage` tường minh: bỏ trống thì nó gọi
     * `Paginator::resolveCurrentPage()`, resolver do Laravel cài lúc boot và đọc `$app['request']`
     * — chạy cả bộ sẽ đỏ "Target class [request] does not exist", chạy riêng một tệp thì không lộ.
     *
     * @param  list<object>  $items
     */
    private function paginator(array $items, int $total = 0, int $currentPage = 1): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, $total ?: count($items), 20, $currentPage);
    }

    /** @param array<string, mixed> $ghiDe */
    private function site(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'name' => 'Công trình A',
            'status' => 'installing',
            'contract_amount' => 100_000_000,
            'labor_cost' => 0,
            'transport_cost' => 0,
            'other_cost' => 0,
            'system_kwp' => null,
            'system_kw_ac' => null,
            'installed_at' => null,
            'warranty_to' => null,
            'technician_name' => null,
            'owner_name' => null,
        ], $ghiDe);
    }

    public function test_gop_tien_va_phan_tram_da_thu(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([
                $this->site(['id' => 1, 'contract_amount' => 100_000_000]),
                $this->site(['id' => 2, 'contract_amount' => 50_000_000, 'labor_cost' => 1_000_000]),
            ]),
            ['received' => [1 => 25_000_000, 2 => 50_000_000], 'materialCost' => [1 => 10_000_000],
                'paymentCost' => [], 'requestCost' => []],
        );

        // 150tr hợp đồng, 75tr đã thu -> 50%
        $this->assertSame('150.000.000 đ', $data['totalContractText']);
        $this->assertSame('75.000.000 đ', $data['totalReceivedText']);
        $this->assertSame(50.0, round($data['totalPaidPercent'], 6));

        // Nợ = max(0, hợp đồng - đã thu) từng dòng: (100-25) + (50-50) = 75tr
        $this->assertSame('75.000.000 đ', $data['totalDebtText']);

        // Lợi nhuận = hợp đồng - chi phí: (100-10) + (50-1) = 139tr
        $this->assertSame('139.000.000 đ', $data['totalProfitText']);
        $this->assertSame(139_000_000.0, round($data['totalProfit'], 6));
    }

    public function test_da_thu_vuot_hop_dong_thi_no_bang_0_va_phan_tram_kep_100(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([$this->site(['id' => 1, 'contract_amount' => 10_000_000])]),
            ['received' => [1 => 30_000_000], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        $this->assertSame('0 đ', $data['totalDebtText'], 'nợ âm phải kẹp về 0 như bản cũ');
        $this->assertSame(100.0, round($data['totalPaidPercent'], 6));
        $this->assertSame(100.0, round($data['sites']->items()[0]->paidPercent, 6));
        // Số ở trên để dựng BỀ RỘNG thanh; chuỗi ở đây để IN — dấu Việt (chốt 2026-09-29).
        $this->assertSame('100,0%', $data['totalPaidPercentText']);
        $this->assertSame('100,0%', $data['sites']->items()[0]->paidPercentText);
    }

    public function test_hop_dong_bang_0_khong_chia_cho_0(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([$this->site(['id' => 1, 'contract_amount' => 0])]),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        $this->assertSame(0.0, round($data['totalPaidPercent'], 6));
        $this->assertSame(0.0, round($data['sites']->items()[0]->paidPercent, 6));
        $this->assertSame('0,0%', $data['totalPaidPercentText'], 'bản cũ in `0.0%` dấu Anh');
        $this->assertSame('0,0%', $data['sites']->items()[0]->paidPercentText);
    }

    public function test_trang_thai_ra_dung_nhan_tong_mau_icon_va_trang_thai_la_roi_ve_mac_dinh(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([
                $this->site(['id' => 1, 'status' => 'planning']),
                $this->site(['id' => 2, 'status' => 'DONE']),
                $this->site(['id' => 3, 'status' => 'la_lung']),
            ]),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        $rows = $data['sites']->items();

        // Tông là CHUỖI LỚP đầy đủ, không phải tên biến thể: view cũ ghép `bg-{{ $tone }}` lúc chạy
        // mà Tailwind quét mã nguồn theo văn bản nên chuỗi ghép động không bao giờ được sinh.
        $this->assertSame(['Chuẩn bị', 'tw:bg-[#6c757d] tw:text-[#ffffff]', 'bi-hourglass-split'],
            [$rows[0]->statusLabel, $rows[0]->statusBadgeClass, $rows[0]->statusIcon]);

        // Bản cũ hạ chữ thường trước khi so -> 'DONE' vẫn khớp.
        $this->assertSame('Hoàn thành', $rows[1]->statusLabel);

        $this->assertSame(['—', 'tw:bg-[#6c757d] tw:text-[#ffffff]', 'bi-dot'],
            [$rows[2]->statusLabel, $rows[2]->statusBadgeClass, $rows[2]->statusIcon]);
    }

    public function test_the_kpi_dem_0_thi_in_gach_dai_rieng_o_tong_in_so(): void
    {
        $rong = (new SiteListPresenter)->viewData(
            $this->paginator([], 0),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        $this->assertSame(['Tổng công trình', '0'], [$rong['kpiCards'][0]->label, $rong['kpiCards'][0]->valueText],
            'ô tổng in thẳng số kể cả 0 — bản cũ không dùng `?:` cho ô này');
        $this->assertSame('—', $rong['kpiCards'][1]->valueText, 'đếm 0 thì in gạch dài');
        $this->assertSame('—', $rong['kpiCards'][2]->valueText);
        $this->assertSame('—', $rong['kpiCards'][3]->valueText);
    }

    public function test_cong_suat_cat_so_0_vo_nghia_va_trong_thi_null(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([
                $this->site(['id' => 1, 'system_kwp' => '10.50', 'system_kw_ac' => '8.00']),
                $this->site(['id' => 2, 'system_kwp' => null, 'system_kw_ac' => '']),
                $this->site(['id' => 3, 'system_kwp' => 1234.5, 'system_kw_ac' => 0]),
            ]),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        $rows = $data['sites']->items();
        $this->assertSame('10.5', $rows[0]->kwpText);
        $this->assertSame('8', $rows[0]->kwText);
        $this->assertNull($rows[1]->kwpText);
        $this->assertNull($rows[1]->kwText, 'chuỗi rỗng cũng là trống');

        // Dấu phân cách MẶC ĐỊNH của PHP (phẩy ngăn nghìn) — giữ đúng bản cũ.
        $this->assertSame('1,234.5', $rows[2]->kwpText);
        // ⚠️ Giá trị 0 KHÔNG ra null: number_format(0,2) = "0.00" -> cắt số 0 ở đuôi -> "0." ->
        // cắt dấu chấm -> "0". Closure cũ cũng vậy. (Bản đầu của test này đoán là null và đỏ.)
        // Hệ quả ở view: `@if(!$kwp && !$kw)` coi chuỗi "0" là FALSY nên vẫn hiện "Chưa nhập".
        $this->assertSame('0', $rows[2]->kwText);
    }

    public function test_tach_ten_nguoi_phu_trach_theo_phay_va_cham_phay(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([
                $this->site(['id' => 1, 'technician_name' => 'Anh Ba, Anh Tư; Chị Năm']),
                $this->site(['id' => 2, 'technician_name' => '']),
                $this->site(['id' => 3, 'owner_name' => 'Chị Sáu', 'technician_name' => 'Bỏ qua']),
            ]),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        $rows = $data['sites']->items();
        $this->assertSame(['Anh Ba', 'Anh Tư', 'Chị Năm'], $rows[0]->ownerChips);

        // ⚠️ `technician_name` là CHUỖI RỖNG thì `??` không bỏ qua (nó chỉ bỏ qua null), nên
        // $owner = '' và tách ra mảng RỖNG — không phải ['—']. Bản cũ y hệt; view rơi vào nhánh
        // `count(...) === 0` nên vẫn in một gạch dài. (Bản đầu của test này đoán ['—'] và đỏ.)
        $this->assertSame([], $rows[1]->ownerChips);

        $this->assertSame(['Chị Sáu'], $rows[2]->ownerChips, 'owner_name được ưu tiên hơn technician_name');
    }

    public function test_so_thu_tu_cong_offset_phan_trang(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([$this->site(['id' => 1]), $this->site(['id' => 2])], 100, 3),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        // Trang 3, mỗi trang 20 -> firstItem = 41.
        $rows = $data['sites']->items();
        $this->assertSame(41, $rows[0]->rowNo);
        $this->assertSame(42, $rows[1]->rowNo);
    }

    public function test_ngay_trong_ra_gach_dai(): void
    {
        $data = (new SiteListPresenter)->viewData(
            $this->paginator([
                $this->site(['id' => 1, 'installed_at' => '2026-03-01', 'warranty_to' => null]),
            ]),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        $row = $data['sites']->items()[0];
        $this->assertSame('01/03/2026', $row->installedAtText);
        $this->assertSame('—', $row->warrantyToText, 'bản cũ in gạch DÀI cho ngày trống');
    }
}
