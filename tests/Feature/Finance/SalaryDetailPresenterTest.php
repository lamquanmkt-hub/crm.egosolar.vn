<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\SalaryComponentRow;
use App\View\Presenters\Finance\SalaryDetailPresenter;
use Tests\TestCase;

/**
 * SalaryDetailPresenter thay 4 khối `@php` của trang phiếu lương chi tiết (2026-09-25).
 *
 * Một khối đầu lọc theo section + chuẩn hoá điểm KPI + khai closure định dạng ba kiểu; ba khối
 * còn lại là `$def=$row->definition;` lặp trong ba vòng lặp.
 */
final class SalaryDetailPresenterTest extends TestCase
{
    private const VIEW = 'resources/views/finance/salary-detail.blade.php';

    private function makeComponent(string $section, array $definition = [], mixed $amount = 0): object
    {
        return (object) [
            'definition' => (object) array_merge([
                'id' => 1, 'label' => 'Thành phần', 'section' => $section,
                'display_format' => 'money', 'editable_amount' => true,
            ], $definition),
            'amount' => $amount,
        ];
    }

    public function test_view_khong_con_php_va_chi_doc_thuoc_tinh_that_cua_dto(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('$def', $source, 'biến của khối @php cũ không được còn');

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(SalaryComponentRow::class))->getProperties()
        );
        $this->assertNotSame([], $m[1]);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)));
    }

    public function test_chia_dung_ba_section_va_bo_section_la(): void
    {
        $data = (new SalaryDetailPresenter)->viewData([
            $this->makeComponent('info'),
            $this->makeComponent('income'),
            $this->makeComponent('income'),
            $this->makeComponent('deduction'),
            $this->makeComponent('khong_biet'),   // section lạ -> bỏ qua, không rơi vào nhóm nào
        ], [], null);

        $this->assertCount(1, $data['infoRows']);
        $this->assertCount(2, $data['incomeRows']);
        $this->assertCount(1, $data['deductionRows']);
    }

    public function test_dinh_dang_theo_display_format(): void
    {
        $data = (new SalaryDetailPresenter)->viewData([
            $this->makeComponent('income', ['display_format' => 'money'], 12500000),
            // Kiểu 'number' dùng dấu phân cách kiểu ANH rồi cắt số 0 vô nghĩa ở cuối.
            $this->makeComponent('info', ['display_format' => 'number'], 21.50),
            $this->makeComponent('info', ['display_format' => 'number'], 1234.00),
            $this->makeComponent('info', ['display_format' => 'percent'], 87.25),
            // display_format lạ -> về money, như nhánh mặc định của closure cũ
            $this->makeComponent('income', ['display_format' => 'la'], 1000),
        ], [], null);

        $this->assertSame('12.500.000 đ', $data['incomeRows'][0]->valueText);
        $this->assertSame('21.5', $data['infoRows'][0]->valueText);
        $this->assertSame('1,234', $data['infoRows'][1]->valueText, 'dấu phẩy nghìn kiểu Anh, bỏ .00');
        $this->assertSame('87,3%', $data['infoRows'][2]->valueText, 'percent làm tròn 1 chữ số thập phân');
        $this->assertSame('1.000 đ', $data['incomeRows'][1]->valueText);
    }

    public function test_so_tho_cho_input_va_co_cho_phep_sua(): void
    {
        $data = (new SalaryDetailPresenter)->viewData([
            $this->makeComponent('income', ['id' => 7, 'label' => 'Lương cơ bản', 'editable_amount' => true], 12500000),
            $this->makeComponent('income', ['id' => 8, 'editable_amount' => false], 500000.75),
        ], [], null);

        $this->assertSame(7, $data['incomeRows'][0]->id);
        $this->assertSame('Lương cơ bản', $data['incomeRows'][0]->label);
        $this->assertTrue($data['incomeRows'][0]->editable);
        $this->assertSame(12500000.0, $data['incomeRows'][0]->amountValue);

        $this->assertFalse($data['incomeRows'][1]->editable);
        $this->assertSame(500000.75, $data['incomeRows'][1]->amountValue);
    }

    public function test_ghi_chu_lay_tu_salary_meta(): void
    {
        $svc = new SalaryDetailPresenter;

        $this->assertSame('Đã đối chiếu', $svc->viewData([], ['note_text' => 'Đã đối chiếu'], null)['noteText']);
        $this->assertSame('', $svc->viewData([], [], null)['noteText']);
    }

    public function test_diem_kpi_khong_co_ban_chup_thi_gach_dai(): void
    {
        $svc = new SalaryDetailPresenter;

        $this->assertSame('—', $svc->viewData([], [], null)['kpiPercentText']);
    }

    public function test_diem_kpi_duoi_2_duoc_coi_la_ty_le_nen_nhan_100(): void
    {
        $svc = new SalaryDetailPresenter;

        // Suy đoán theo dữ liệu cũ: 0,85 nghĩa là 85%. Giữ nguyên vì đổi là đổi số hiển thị.
        $this->assertSame('85,0%', $svc->viewData([], [], (object) ['total_kpi_percent' => 0.85])['kpiPercentText']);
        $this->assertSame('200,0%', $svc->viewData([], [], (object) ['total_kpi_percent' => 2])['kpiPercentText']);
        // Trên 2 thì coi là đã ở dạng phần trăm
        $this->assertSame('2,1%', $svc->viewData([], [], (object) ['total_kpi_percent' => 2.1])['kpiPercentText']);
        $this->assertSame('87,5%', $svc->viewData([], [], (object) ['total_kpi_percent' => 87.5])['kpiPercentText']);
        // 0 không nhân (điều kiện là > 0)
        $this->assertSame('0,0%', $svc->viewData([], [], (object) ['total_kpi_percent' => 0])['kpiPercentText']);
    }
}
