<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\SettlementRequestListPresenter;
use Tests\TestCase;

/**
 * `SettlementRequestListPresenter` — thay khối `@php` phòng hờ và mọi phép tính mà
 * `settlement_requests/index` làm ngay trong Blade (2026-09-30).
 */
final class SettlementRequestListPresenterTest extends TestCase
{
    private const LABELS = [
        'draft' => 'Nháp',
        'submitted' => 'Chờ QL tài chính duyệt hoàn ứng',
        'admin_approved' => 'Chờ kế toán đối soát',
        'accounting_approved' => 'Đã quyết toán',
    ];

    /** @param array<string, mixed> $ghiDe */
    private function phieu(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 5, 'code' => 'ST-0005', 'created_by' => 77,
            'company' => 'EGO', 'recipient_name' => 'Nguyễn A',
            'advance_amount' => '12500000.00', 'actual_amount' => '11000000.00',
            'refund_amount' => '1500000.00', 'difference_amount' => '-1500000.00',
            'settlement_type' => 'refund', 'reason' => 'Quyết toán', 'note' => 'ghi chú',
            'status' => 'draft', 'created_at' => '2026-09-20 08:30:00',
            'attachments' => ['settlements/a.pdf', 'settlements/b.jpg'],
            'advanceRequest' => (object) ['id' => 900, 'code' => 'AR-0900'],
        ], $ghiDe);
    }

    /**
     * @param  iterable<object>  $items
     * @param  iterable<object>  $advances
     * @return array<string, mixed>
     */
    private function chay(
        iterable $items = [],
        iterable $advances = [],
        ?int $uid = 77,
        bool $viewAll = false,
        bool $ql = false,
        bool $kt = false,
        array $oldInput = [],
        array $stats = [],
    ): array {
        return (new SettlementRequestListPresenter)->viewData(
            items: $items, advances: $advances, labels: self::LABELS,
            currentUserId: $uid, canViewAll: $viewAll,
            canApproveManagement: $ql, canApproveAccounting: $kt,
            stats: $stats, pageCount: 0, totalCount: 0,
            rawFilters: [], oldInput: $oldInput,
        );
    }

    public function test_dong_bang_dinh_dang_tien_ngay_va_chung_tu(): void
    {
        $row = $this->chay([$this->phieu()])['settlementRows'][0];

        $this->assertSame('12.500.000 đ', $row->advanceAmountText);
        $this->assertSame('11.000.000 đ', $row->actualAmountText);
        $this->assertSame('20/09/2026 08:30', $row->createdAtText);
        $this->assertSame('AR-0900', $row->advanceCode);
        $this->assertSame(900, $row->advanceRequestId);
        $this->assertSame('Nháp', $row->statusLabel);
        $this->assertSame('Xóa phiếu ST-0005?', $row->deleteConfirmText);

        // Chứng từ: số thứ tự hiển thị bắt đầu từ 1, đúng `$i+1` của bản cũ.
        $this->assertSame(
            [['index' => 1, 'path' => 'settlements/a.pdf'], ['index' => 2, 'path' => 'settlements/b.jpg']],
            $row->attachments
        );

        $khongCo = $this->chay([$this->phieu(['attachments' => null])])['settlementRows'][0];
        $this->assertSame([], $khongCo->attachments, 'null ép sang mảng rỗng, view in gạch ngang');
    }

    public function test_ket_qua_doi_soat_ba_nhanh_va_giu_max0_cua_ban_cu(): void
    {
        $lam = fn (array $g) => $this->chay([$this->phieu($g)])['settlementRows'][0];

        $refund = $lam(['settlement_type' => 'refund']);
        $this->assertSame('Hoàn lại 1.500.000 đ', $refund->outcomeText);
        $this->assertSame('tw:text-[#087f98]', $refund->outcomeClass);

        $payMore = $lam(['settlement_type' => 'pay_more', 'difference_amount' => '1500000.00']);
        $this->assertSame('Cty trả thêm 1.500.000 đ', $payMore->outcomeText);
        $this->assertSame('tw:text-[#c2410c]', $payMore->outcomeClass);

        // `max(0, …)` của bản cũ: chênh lệch ÂM thì in 0, không in số âm.
        $amThanhKhong = $lam(['settlement_type' => 'pay_more', 'difference_amount' => '-2000000.00']);
        $this->assertSame('Cty trả thêm 0 đ', $amThanhKhong->outcomeText);

        $balanced = $lam(['settlement_type' => 'balanced']);
        $this->assertSame('Khớp đủ', $balanced->outcomeText);
        $this->assertSame('tw:text-[#15803d]', $balanced->outcomeClass);

        // Loại lạ rơi vào nhánh mặc định, y bản cũ (`@else`).
        $this->assertSame('Khớp đủ', $lam(['settlement_type' => 'loai_la'])->outcomeText);
    }

    public function test_phieu_khong_gan_tam_ung_thi_ma_rong(): void
    {
        $row = $this->chay([$this->phieu(['advanceRequest' => null])])['settlementRows'][0];

        $this->assertSame('', $row->advanceCode, 'view dựa vào chuỗi rỗng để in gạch ngang');
        $this->assertSame(0, $row->advanceRequestId);
    }

    public function test_nut_thao_tac_theo_chu_phieu_va_quyen(): void
    {
        foreach (['draft', 'admin_rejected', 'accounting_rejected'] as $st) {
            $this->assertTrue($this->chay([$this->phieu(['status' => $st])])['settlementRows'][0]->canSubmit, $st);
        }
        $this->assertFalse($this->chay([$this->phieu(['status' => 'submitted'])])['settlementRows'][0]->canSubmit);
        $this->assertFalse($this->chay([$this->phieu()], uid: 999)['settlementRows'][0]->canSubmit, 'người khác không gửi được');

        $this->assertTrue($this->chay([$this->phieu(['status' => 'submitted'])], ql: true)['settlementRows'][0]->canManagementApprove);
        $this->assertTrue($this->chay([$this->phieu(['status' => 'admin_approved'])], kt: true)['settlementRows'][0]->canAccountingApprove);

        // Xoá: người xem-tất-cả xoá được MỌI trạng thái; chủ phiếu chỉ xoá được bản nháp.
        $this->assertTrue($this->chay([$this->phieu(['status' => 'accounting_approved'])], viewAll: true)['settlementRows'][0]->canDelete);
        $this->assertTrue($this->chay([$this->phieu(['status' => 'draft'])])['settlementRows'][0]->canDelete);
        $this->assertFalse($this->chay([$this->phieu(['status' => 'submitted'])])['settlementRows'][0]->canDelete, 'chủ phiếu không xoá được phiếu đã gửi');
        $this->assertFalse($this->chay([$this->phieu(['status' => 'draft'])], uid: 999)['settlementRows'][0]->canDelete);
    }

    public function test_o_chon_phieu_tam_ung_giu_dung_du_lieu_cua_ban_cu(): void
    {
        $advances = [
            (object) ['id' => 11, 'code' => 'AR-11', 'amount' => '12500000.00', 'recipient_name' => 'Nguyễn A',
                'company' => 'EGO', 'settlement_due_date' => '2026-10-15'],
            (object) ['id' => 12, 'code' => 'AR-12', 'amount' => '500000.00', 'recipient_name' => 'Trần B',
                'company' => null, 'settlement_due_date' => null],
        ];

        $ds = $this->chay(advances: $advances, oldInput: ['advance_request_id' => '12'])['advanceOptions'];

        $this->assertSame('AR-11 — Nguyễn A — 12.500.000 đ', $ds[0]->label);
        $this->assertSame(12500000.0, $ds[0]->amount, 'Alpine cần dạng SỐ để tính chênh lệch');
        $this->assertSame('15/10/2026', $ds[0]->dueText);
        $this->assertFalse($ds[0]->selected);

        // Không có hạn → gạch NGẮN `-`, đúng bản cũ (khác gạch dài `—` của DisplayFormat::date).
        $this->assertSame('-', $ds[1]->dueText);
        // Công ty trống giữ NGUYÊN chuỗi rỗng — `data-company` của bản cũ cũng rỗng.
        $this->assertSame('', $ds[1]->company);
        $this->assertTrue($ds[1]->selected, 'old input chọn đúng phiếu');
    }

    public function test_o_chi_so_dung_dau_phan_cach_tieng_viet(): void
    {
        $kpi = (new SettlementRequestListPresenter)->viewData(
            items: [], advances: [], labels: self::LABELS, currentUserId: 1,
            canViewAll: false, canApproveManagement: false, canApproveAccounting: false,
            stats: ['total' => 1234, 'pending' => 5, 'done' => 2, 'refund' => 9_000_000],
            pageCount: 20, totalCount: 1234, rawFilters: [], oldInput: [],
        )['kpi'];

        $this->assertSame('1.234', $kpi->total, 'bản cũ dùng number_format trơn (1,234)');
        $this->assertSame('9.000.000 đ', $kpi->refundText);
        $this->assertSame('1.234', $kpi->totalCount);
    }
}
