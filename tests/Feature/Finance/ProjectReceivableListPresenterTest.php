<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\ProjectReceivableListPresenter;
use Tests\TestCase;

/**
 * `ProjectReceivableListPresenter` — thay 2 khối `@php` của `finance/project-receivables/index`
 * (2026-10-01).
 */
final class ProjectReceivableListPresenterTest extends TestCase
{
    /** @param array<string, mixed> $ghiDe */
    private function congTrinh(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 7, 'project_code' => 'CT-A1', 'name' => 'Điện mặt trời Bình Dương',
            'address' => '12 Nguyễn Trãi', 'contact_phone' => '0909000111',
            'company_id' => 3, 'progress_percent' => 45, 'project_phase' => 'Thi công',
            'status' => 'in_progress',
            'finance_customer_name' => 'Công ty ABC',
            'finance_contract_amount' => 500000000.0,
            'finance_received_amount' => 200000000.0,
            'finance_receivable_amount' => 300000000.0,
            'finance_overdue_amount' => 0.0,
            'finance_overpaid_amount' => 0.0,
            'finance_payments_count' => 3,
            'finance_status' => 'partial',
            'finance_next_term' => (object) [
                'name' => 'Đợt 2', 'finance_remaining_amount' => 150000000.0, 'due_date' => '2026-11-30',
            ],
        ], $ghiDe);
    }

    /** @param array<string, mixed> $ghiDe */
    private function dong(array $ghiDe = []): object
    {
        return (new ProjectReceivableListPresenter)
            ->viewData([$this->congTrinh($ghiDe)], [])['receivableRows'][0];
    }

    public function test_dinh_dang_tien_va_chuoi_ghep(): void
    {
        $d = $this->dong();

        $this->assertSame('CT-A1', $d->codeText);
        $this->assertSame('500.000.000 đ', $d->contractText);
        $this->assertSame('200.000.000 đ', $d->receivedText);
        $this->assertSame('300.000.000 đ', $d->receivableText);
        $this->assertSame('3 lần thu', $d->paymentsCountText);
        $this->assertSame('Công ty #3', $d->companyText);
        $this->assertSame('Tiến độ: 45% · Thi công', $d->progressText);
        $this->assertSame('0909000111', $d->phoneText);
    }

    public function test_gia_tri_trong_ra_dung_mac_dinh_cua_ban_cu(): void
    {
        $d = $this->dong([
            'project_code' => null, 'address' => '', 'contact_phone' => null,
            'company_id' => null, 'progress_percent' => null,
            'project_phase' => null, 'status' => null,
        ]);

        $this->assertSame('CT-7', $d->codeText, 'không có mã thì ghép CT-<id>');
        $this->assertSame('Chưa có địa chỉ', $d->addressText);
        $this->assertSame('Chưa có SĐT', $d->phoneText);
        $this->assertSame('', $d->companyText, 'view dựa vào chuỗi rỗng để không in dòng công ty');
        $this->assertSame('Tiến độ: 0% · —', $d->progressText, 'thiếu cả phase lẫn status thì gạch dài');

        // Có `status` mà không có `project_phase` thì in `status`.
        $this->assertSame('Tiến độ: 0% · in_progress',
            $this->dong(['progress_percent' => 0, 'project_phase' => null])->progressText);
    }

    public function test_sau_trang_thai_cho_dung_nhan_va_tong(): void
    {
        $mong = [
            'unpaid' => ['Chưa thu', 'tw:bg-[#ffe4e6] tw:text-[#be123c]'],
            'partial' => ['Đang thu', 'tw:bg-[#fff7d6] tw:text-[#a16207]'],
            'overdue' => ['Quá hạn', 'tw:bg-[#ffe4e6] tw:text-[#be123c]'],
            'paid' => ['Đã thu đủ', 'tw:bg-[#dcfce7] tw:text-[#15803d]'],
            'overpaid' => ['Thu vượt', 'tw:bg-[#fff7d6] tw:text-[#a16207]'],
        ];

        foreach ($mong as $ma => [$nhan, $lop]) {
            $d = $this->dong(['finance_status' => $ma]);
            $this->assertSame($nhan, $d->statusText, $ma);
            $this->assertSame($lop, $d->statusToneClass, $ma);
        }

        $la = $this->dong(['finance_status' => 'trang_thai_la']);
        $this->assertSame('Chưa xác định', $la->statusText);
        $this->assertSame('tw:bg-[#fff7d6] tw:text-[#a16207]', $la->statusToneClass, 'tông vàng mặc định');
    }

    public function test_dong_phu_chi_hien_khi_vuot_nguong(): void
    {
        // Bản cũ: `@if(($row->finance_overpaid_amount ?? 0) > 1000)` — ngưỡng 1.000 đ.
        $this->assertNull($this->dong(['finance_overpaid_amount' => 1000])->overpaidText, 'đúng 1.000 thì chưa hiện');
        $this->assertSame('Thu vượt 1.001 đ', $this->dong(['finance_overpaid_amount' => 1001])->overpaidText);

        // Quá hạn thì chỉ cần > 0.
        $this->assertNull($this->dong(['finance_overdue_amount' => 0])->overdueText);
        $this->assertSame('Quá hạn 5.000.000 đ', $this->dong(['finance_overdue_amount' => 5000000])->overdueText);
    }

    public function test_dot_ke_tiep(): void
    {
        $co = $this->dong();
        $this->assertTrue($co->hasNextTerm);
        $this->assertSame('Đợt 2', $co->nextTermName);
        $this->assertSame('150.000.000 đ', $co->nextTermAmountText);
        $this->assertSame('Hạn 30/11/2026', $co->nextTermDueText);

        $khongHan = $this->dong(['finance_next_term' => (object) [
            'name' => 'Đợt 3', 'finance_remaining_amount' => 0, 'due_date' => null,
        ]]);
        $this->assertSame('Chưa đặt hạn', $khongHan->nextTermDueText);

        $khong = $this->dong(['finance_next_term' => null]);
        $this->assertFalse($khong->hasNextTerm, 'view in "Không còn đợt chờ thu"');
    }

    public function test_o_chi_so_dung_dau_phan_cach_tieng_viet(): void
    {
        $kpi = (new ProjectReceivableListPresenter)->viewData([], [
            'project_count' => 1234, 'contract_amount' => 500000000,
            'received_amount' => 200000000, 'receivable_amount' => 300000000,
            'overdue_amount' => 5000000, 'overpaid_amount' => 1000,
        ])['kpi'];

        // ĐỔI ĐẦU RA có chủ ý: bản cũ dùng `number_format()` trơn cho số đếm (`1,234`).
        $this->assertSame('1.234', $kpi->projectCount);
        $this->assertSame('500.000.000 đ', $kpi->contractAmount);
        $this->assertSame('200.000.000 đ', $kpi->receivedAmount);
        $this->assertSame('300.000.000 đ', $kpi->receivableAmount);
        $this->assertSame('5.000.000 đ', $kpi->overdueAmount);
        $this->assertSame('1.000 đ', $kpi->overpaidAmount);
    }
}
