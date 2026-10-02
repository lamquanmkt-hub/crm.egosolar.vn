<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\AssetFormValues;
use App\View\Presenters\Finance\AssetListPresenter;
use Tests\TestCase;

/**
 * `AssetListPresenter` — thay 2 khối `@php` của trang `finance/assets` (2026-09-30).
 *
 * Bốn hành vi dễ tưởng là lỗi, đều giữ Y bản cũ và được khẳng định ở đây:
 *  1. chi phí sự kiện `"0.00"` VẪN hiện `0 đ` (chuỗi "0.00" là truthy trong PHP);
 *  2. trạng thái ngoài bảng màu cho chuỗi lớp RỖNG, không rơi về một tông mặc định nào;
 *  3. `assigned_name` là chuỗi rỗng thì ra `Chưa bàn giao` (`?:`, không phải `??`);
 *  4. giá trị biểu mẫu là CHUỖI cho mọi trường, kể cả id và số tháng.
 */
final class AssetListPresenterTest extends TestCase
{
    /** @param array<string, mixed> $ghiDe */
    private function taiSan(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 7, 'code' => 'TS-0007', 'name' => 'Máy hàn',
            'serial_no' => 'SN-1', 'location' => 'Kho A',
            'category_name' => 'Máy móc', 'category_color' => '#2563eb',
            'company_name' => 'EGO', 'assigned_name' => 'Nguyễn A', 'department' => 'Kỹ thuật',
            'original_cost' => '150000000.00', 'book_value' => 145000000.0,
            'accumulated_depreciation' => 5000000.0, 'monthly_depreciation' => 2416666.67,
            'progress_percent' => 3.3, 'used_months' => 2, 'useful_life_months' => 60,
            'status' => 'active', 'condition' => 'good',
            'next_maintenance_date' => '2026-10-10', 'warranty_until' => null,
            'events' => [], 'files' => [],
            'category_id' => 3, 'company_id' => 1, 'assigned_to' => 9,
            'purchase_date' => '2025-03-15', 'start_use_date' => '2025-04-01',
            'salvage_value' => '5000000.00', 'depreciation_method' => 'straight_line',
            'vendor' => 'NCC', 'invoice_no' => 'HD-1', 'note' => 'ghi chú',
        ], $ghiDe);
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function bangNhan(): array
    {
        return [
            ['active' => 'Đang sử dụng', 'idle' => 'Nhàn rỗi', 'lost' => 'Mất/hỏng'],
            ['good' => 'Tốt', 'worn' => 'Hao mòn'],
            ['purchase' => 'Ghi nhận mua mới', 'maintenance' => 'Bảo trì'],
        ];
    }

    public function test_dong_bang_in_dung_tien_ngay_va_chuoi_ghep(): void
    {
        [$st, $cd, $et] = $this->bangNhan();

        $data = (new AssetListPresenter)->viewData(
            assets: [$this->taiSan()],
            statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: [], sttOffset: 30, summary: [],
        );

        $row = $data['assetRows'][0];

        $this->assertSame(31, $row->stt, 'STT phải cộng offset trang');
        $this->assertSame('150.000.000 đ', $row->costText);
        $this->assertSame('145.000.000 đ', $row->bookValueText);
        $this->assertSame('2.416.667 đ', $row->monthlyText, 'làm tròn 0 chữ số thập phân');
        $this->assertSame('Nguyễn A · Kỹ thuật', $row->assignedText);
        $this->assertSame('2/60 tháng', $row->usageText);
        $this->assertSame('3.3', $row->progressPercentText, 'in thẳng vào width:…%, không định dạng kiểu Việt');
        $this->assertSame('10/10/2026', $row->nextMaintenanceText);
        $this->assertSame('—', $row->warrantyText, 'ngày trống ra gạch dài');
        $this->assertSame('tw:bg-[#dcfce7] tw:text-[#047857]', $row->statusBadgeClass);
        $this->assertSame('Đang sử dụng', $row->statusLabel);
        $this->assertSame('Tốt', $row->conditionLabel);
    }

    public function test_gia_tri_trong_ra_dung_mac_dinh_cua_ban_cu(): void
    {
        [$st, $cd, $et] = $this->bangNhan();

        $data = (new AssetListPresenter)->viewData(
            assets: [$this->taiSan([
                'serial_no' => null, 'location' => '', 'category_name' => null,
                'category_color' => null, 'company_name' => '',
                // CHUỖI RỖNG chứ không null: `??` sẽ bỏ qua, `?:` thì không — bản cũ dùng `?:`.
                'assigned_name' => '', 'department' => null,
            ])],
            statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: [], sttOffset: 0, summary: [],
        );

        $row = $data['assetRows'][0];

        $this->assertSame('—', $row->serialText);
        $this->assertSame('—', $row->locationText);
        $this->assertSame('Chưa phân nhóm', $row->categoryName);
        $this->assertSame('#0ea5e9', $row->categoryColor);
        $this->assertSame('—', $row->companyName);
        $this->assertSame('Chưa bàn giao', $row->assignedText, 'không có bộ phận thì không có dấu ·');
    }

    public function test_trang_thai_ngoai_bang_mau_cho_lop_rong_va_giu_nguyen_ma(): void
    {
        [$st, $cd, $et] = $this->bangNhan();

        $data = (new AssetListPresenter)->viewData(
            assets: [$this->taiSan(['status' => 'trang_thai_la', 'condition' => 'tinh_trang_la'])],
            statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: [], sttOffset: 0, summary: [],
        );

        $row = $data['assetRows'][0];

        $this->assertSame('', $row->statusBadgeClass, 'huy hiệu chỉ còn hình dạng, đúng bản cũ');
        $this->assertSame('trang_thai_la', $row->statusLabel, 'không có nhãn thì in nguyên mã');
        $this->assertSame('tinh_trang_la', $row->conditionLabel);
    }

    public function test_chi_phi_su_kien_bang_khong_van_hien_vi_chuoi_0_00_la_truthy(): void
    {
        [$st, $cd, $et] = $this->bangNhan();

        $data = (new AssetListPresenter)->viewData(
            assets: [$this->taiSan(['events' => [
                (object) ['type' => 'purchase', 'event_date' => '2025-03-15', 'amount' => '150000000.00', 'note' => 'Mua mới'],
                (object) ['type' => 'maintenance', 'event_date' => null, 'amount' => null, 'note' => null],
                (object) ['type' => 'loai_la', 'event_date' => '2026-01-20', 'amount' => '0.00', 'note' => 'chi phí 0'],
                (object) ['type' => 'note', 'event_date' => '2026-01-21', 'amount' => 0, 'note' => 'chi phí 0 dạng số'],
            ]])],
            statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: [], sttOffset: 0, summary: [],
        );

        $events = $data['assetRows'][0]->events;

        $this->assertSame('Ghi nhận mua mới', $events[0]->typeLabel);
        $this->assertSame('150.000.000 đ', $events[0]->amountText);

        $this->assertSame('—', $events[1]->dateText, 'ngày trống');
        $this->assertSame('—', $events[1]->noteText, 'ghi chú trống');
        $this->assertNull($events[1]->amountText, 'amount null thì không in dòng chi phí');

        $this->assertSame('loai_la', $events[2]->typeLabel, 'loại lạ in nguyên mã');
        $this->assertSame('0 đ', $events[2]->amountText,
            'decimal MariaDB trả chuỗi "0.00" — chuỗi này TRUTHY nên bản cũ VẪN in dòng chi phí');

        $this->assertNull($events[3]->amountText, 'còn số 0 thật thì falsy, không in — khác chuỗi "0.00"');
    }

    public function test_tep_dinh_kem_khong_co_ten_goc_thi_goi_la_file(): void
    {
        [$st, $cd, $et] = $this->bangNhan();

        $data = (new AssetListPresenter)->viewData(
            assets: [$this->taiSan(['files' => [
                (object) ['id' => 11, 'original_name' => 'hoa-don.pdf'],
                (object) ['id' => 12, 'original_name' => null],
                (object) ['id' => 13, 'original_name' => ''],
            ]])],
            statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: [], sttOffset: 0, summary: [],
        );

        $files = $data['assetRows'][0]->files;

        $this->assertSame([11, 12, 13], array_map(static fn ($f) => $f->id, $files));
        $this->assertSame(['hoa-don.pdf', 'File', 'File'], array_map(static fn ($f) => $f->name, $files));
    }

    public function test_bieu_mau_uu_tien_old_input_roi_moi_den_tai_san(): void
    {
        [$st, $cd, $et] = $this->bangNhan();

        $data = (new AssetListPresenter)->viewData(
            assets: [$this->taiSan()],
            statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: ['name' => 'Tên vừa gõ', 'original_cost' => '12345.500'],
            sttOffset: 0, summary: [],
        );

        $form = $data['assetRows'][0]->form;

        $this->assertInstanceOf(AssetFormValues::class, $form);
        $this->assertSame('Tên vừa gõ', $form->name, 'old input thắng giá trị của tài sản');
        $this->assertSame('TS-0007', $form->code, 'trường không có trong old input thì lấy của tài sản');
        $this->assertSame('12345.5', $form->originalCost, 'cắt số 0 vô nghĩa, dấu CHẤM thập phân');
        $this->assertSame('5000000', $form->salvageValue, 'decimal "5000000.00" ra số nguyên');
        $this->assertSame('3', $form->categoryId, 'id luôn là CHUỖI để @selected so bằng === không hụt');
        $this->assertSame('60', $form->usefulLifeMonths);

        // Biểu mẫu thêm mới: không có tài sản nào nên rơi về mặc định của bản cũ.
        $moi = $data['createForm'];
        $this->assertSame('Tên vừa gõ', $moi->name, 'old input áp cho CẢ biểu mẫu thêm mới — đúng bản cũ');
        $this->assertSame('', $moi->code);
        $this->assertSame('36', $moi->usefulLifeMonths);
        $this->assertSame('straight_line', $moi->depreciationMethod);
        $this->assertSame('active', $moi->status);
        $this->assertSame('good', $moi->condition);
        // Old input áp cho CẢ biểu mẫu thêm mới, kể cả trường số — test đầu tiên đoán '0' và đỏ:
        // test sai, không phải code. Chỉ khi KHÔNG có old input thì mới rơi về mặc định 0.
        $this->assertSame('12345.5', $moi->originalCost);

        $khongOld = (new AssetListPresenter)->viewData(
            assets: [], statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: [], sttOffset: 0, summary: [],
        )['createForm'];
        $this->assertSame('0', $khongOld->originalCost, 'không có old input thì mặc định là 0, không phải rỗng');
        $this->assertSame('', $khongOld->name);
    }

    public function test_o_chi_so_dung_dau_phan_cach_tieng_viet(): void
    {
        [$st, $cd, $et] = $this->bangNhan();

        $data = (new AssetListPresenter)->viewData(
            assets: [], statuses: $st, conditions: $cd, eventTypes: $et,
            oldInput: [], sttOffset: 0,
            summary: ['count' => 1234, 'active' => 1200, 'total_cost' => 9_000_000,
                'book_value' => 8_000_000, 'accumulated' => 1_000_000, 'maintenance_warning' => 5],
        );

        $kpi = $data['summaryCards'];

        // Bản cũ dùng `number_format()` TRƠN (dấu Anh `1,234`) ngay cạnh các ô tiền kiểu Việt.
        // Nay thống nhất theo quyết định đã chốt ở đợt 2026-09-29 (11). Đầu ra ĐỔI khi số ≥ 1.000.
        $this->assertSame('1.234', $kpi->countText);
        $this->assertSame('1.200', $kpi->activeText);
        $this->assertSame('5', $kpi->warningText, 'dưới 1.000 thì không khác bản cũ');
        $this->assertSame('9.000.000 đ', $kpi->costText);
        $this->assertSame('8.000.000 đ', $kpi->bookValueText);
        $this->assertSame('1.000.000 đ', $kpi->accumulatedText);
    }
}
