<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\AdvanceRequestListPresenter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `AdvanceRequestListPresenter` — thay 2 khối `@php` của `advance_requests/index` (2026-09-30).
 *
 * Khối cũ là closure `$stageFor` 32 dòng: nó quyết định tiến độ hồ sơ theo TỔ HỢP trạng thái tạm
 * ứng × trạng thái hoàn ứng, và tự gọi `Carbon::parse()` + `now()` để đếm ngày quá hạn.
 */
final class AdvanceRequestListPresenterTest extends TestCase
{
    private const LABELS = [
        'draft' => 'Nháp',
        'pending' => 'Chờ QL tài chính duyệt',
        'submitted' => 'Chờ QL tài chính duyệt',
        'admin_approved' => 'Chờ kế toán chi',
        'admin_rejected' => 'QL tài chính từ chối',
        'accounting_approved' => 'Đã chi • Cần hoàn ứng',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    /** @param array<string, mixed> $ghiDe */
    private function phieu(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 5, 'code' => 'AR-0005', 'created_by' => 77,
            'company' => 'EGO Việt Nam', 'recipient_name' => 'Nguyễn A',
            'amount' => '12500000.00', 'reason' => 'Chi phí công tác',
            'settlement_due_date' => '2026-10-15', 'note' => 'ghi chú',
            'status' => 'draft', 'created_at' => '2026-09-10 08:30:00',
            'creator' => (object) ['name' => 'Người Tạo'],
            'settlementRequest' => null,
        ], $ghiDe);
    }

    /**
     * @param  iterable<object>  $items
     * @return array<string, mixed>
     */
    private function chay(iterable $items, ?int $uid = 77, bool $ql = false, bool $kt = false): array
    {
        return (new AdvanceRequestListPresenter)->viewData(
            items: $items,
            labels: self::LABELS,
            currentUserId: $uid,
            canApproveManagement: $ql,
            canApproveAccounting: $kt,
            today: Carbon::parse('2026-09-30')->startOfDay(),
            stats: [],
            pageCount: 0,
            totalCount: 0,
            rawFilters: [],
            oldInput: [],
            currentUserName: 'Người Dùng',
        );
    }

    public function test_sau_nhanh_trang_thai_tam_ung_cho_dung_nhan_tong_va_icon(): void
    {
        $mong = [
            'draft' => ['Nháp', 'bi-pencil', 'Chưa gửi phê duyệt'],
            'submitted' => ['Chờ QL tài chính duyệt', 'bi-hourglass-split', 'Giai đoạn tạm ứng'],
            'admin_approved' => ['Chờ kế toán chi', 'bi-cash-stack', 'QL tài chính đã duyệt'],
            'admin_rejected' => ['QL tài chính từ chối', 'bi-x-circle', 'Cần chỉnh sửa / gửi lại'],
            'accounting_rejected' => ['Kế toán từ chối chi', 'bi-x-circle', 'Cần chỉnh sửa / gửi lại'],
        ];

        foreach ($mong as $status => [$label, $icon, $note]) {
            $stage = $this->chay([$this->phieu(['status' => $status])])['advanceRows'][0]->stage;
            $this->assertSame($label, $stage->label, "nhãn của {$status}");
            $this->assertSame($icon, $stage->icon, "icon của {$status}");
            $this->assertSame($note, $stage->note, "ghi chú của {$status}");
            $this->assertFalse($stage->overdue);
        }
    }

    public function test_trang_thai_la_tra_ve_nhan_tra_bang_hoac_nguyen_ma(): void
    {
        $stage = $this->chay([$this->phieu(['status' => 'pending'])])['advanceRows'][0]->stage;
        $this->assertSame('Chờ QL tài chính duyệt', $stage->label, 'mã cũ `pending` vẫn tra được nhãn');

        $la = $this->chay([$this->phieu(['status' => 'trang_thai_la'])])['advanceRows'][0]->stage;
        $this->assertSame('trang_thai_la', $la->label, 'không có nhãn thì in nguyên mã');
        $this->assertSame('bi-circle', $la->icon);
        $this->assertSame('', $la->note);
    }

    public function test_da_chi_chua_hoan_ung_thi_dem_dung_so_ngay_qua_han(): void
    {
        // Hạn 25/09, "hôm nay" 30/09 → quá 5 ngày.
        $quaHan = $this->chay([$this->phieu(['status' => 'accounting_approved', 'settlement_due_date' => '2026-09-25'])])['advanceRows'][0]->stage;
        $this->assertSame('Quá hạn hoàn ứng 5 ngày', $quaHan->label);
        $this->assertTrue($quaHan->overdue, 'cột hạn hoàn ứng phải được tô đỏ');
        $this->assertSame('Đã chi • Cần hoàn ứng ngay', $quaHan->note);

        // Đúng hôm nay thì CHƯA quá hạn (bản cũ dùng `lt`, không phải `lte`).
        $homNay = $this->chay([$this->phieu(['status' => 'accounting_approved', 'settlement_due_date' => '2026-09-30'])])['advanceRows'][0]->stage;
        $this->assertSame('Cần hoàn ứng', $homNay->label);
        $this->assertFalse($homNay->overdue);

        $khongHan = $this->chay([$this->phieu(['status' => 'accounting_approved', 'settlement_due_date' => null])])['advanceRows'][0]->stage;
        $this->assertSame('Cần hoàn ứng', $khongHan->label);
        $this->assertSame('—', $this->chay([$this->phieu(['status' => 'accounting_approved', 'settlement_due_date' => null])])['advanceRows'][0]->dueDateText);
    }

    public function test_sau_trang_thai_ho_so_hoan_ung_cho_dung_tien_do(): void
    {
        $mong = [
            'draft' => 'Hoàn ứng nháp',
            'submitted' => 'Chờ QL tài chính duyệt hoàn ứng',
            'admin_rejected' => 'QL tài chính từ chối hoàn ứng',
            'admin_approved' => 'Chờ kế toán đối soát',
            'accounting_rejected' => 'Kế toán từ chối đối soát',
            'accounting_approved' => 'Đã quyết toán',
        ];

        foreach ($mong as $ss => $label) {
            $row = $this->chay([$this->phieu([
                'status' => 'accounting_approved',
                'settlementRequest' => (object) ['id' => 9, 'status' => $ss, 'settlement_type' => 'balanced', 'actual_amount' => 0, 'refund_amount' => 0, 'difference_amount' => 0],
            ])])['advanceRows'][0];
            $this->assertSame($label, $row->stage->label, "hồ sơ hoàn ứng {$ss}");
        }

        // Hồ sơ hoàn ứng ở trạng thái LẠ: bản cũ không gán lại `$stage` nên giữ giá trị khởi tạo,
        // tức nhãn tra theo trạng thái TẠM ỨNG chứ không phải trạng thái hoàn ứng.
        $la = $this->chay([$this->phieu([
            'status' => 'accounting_approved',
            'settlementRequest' => (object) ['id' => 9, 'status' => 'trang_thai_la', 'settlement_type' => 'balanced', 'actual_amount' => 0, 'refund_amount' => 0, 'difference_amount' => 0],
        ])])['advanceRows'][0];
        $this->assertSame('Đã chi • Cần hoàn ứng', $la->stage->label);
    }

    public function test_ket_qua_quyet_toan_theo_ba_loai(): void
    {
        $lam = fn (string $loai) => $this->chay([$this->phieu([
            'status' => 'accounting_approved',
            'settlementRequest' => (object) [
                'id' => 9, 'status' => 'accounting_approved', 'settlement_type' => $loai,
                'actual_amount' => '11000000.00', 'refund_amount' => '1500000.00', 'difference_amount' => '-1500000.00',
            ],
        ])])['advanceRows'][0];

        $this->assertSame('Hoàn lại 1.500.000 đ', $lam('refund')->outcomeLabel);
        $this->assertSame('Công ty trả thêm 1.500.000 đ', $lam('pay_more')->outcomeLabel, 'lấy trị tuyệt đối của chênh lệch');
        $this->assertSame('Khớp đủ', $lam('balanced')->outcomeLabel);
        $this->assertSame('Đã chi 11.000.000 / 12.500.000 đ', $lam('refund')->outcomeSub);

        $chuaLap = $this->chay([$this->phieu(['status' => 'accounting_approved'])])['advanceRows'][0];
        $this->assertSame('Chưa lập hoàn ứng', $chuaLap->outcomeLabel);
        $this->assertSame('Tạm ứng 12.500.000 đ', $chuaLap->outcomeSub);

        $chuaChi = $this->chay([$this->phieu(['status' => 'draft'])])['advanceRows'][0];
        $this->assertSame('—', $chuaChi->outcomeLabel);
        $this->assertSame('Chưa phát sinh hoàn ứng', $chuaChi->outcomeSub);
    }

    public function test_nut_thao_tac_theo_chu_phieu_va_quyen(): void
    {
        // Chủ phiếu: được gửi duyệt ở 3 trạng thái, được lập hoàn ứng khi đã chi.
        foreach (['draft', 'admin_rejected', 'accounting_rejected'] as $st) {
            $this->assertTrue($this->chay([$this->phieu(['status' => $st])])['advanceRows'][0]->canSubmit, $st);
        }
        $this->assertFalse($this->chay([$this->phieu(['status' => 'submitted'])])['advanceRows'][0]->canSubmit);

        // Người KHÁC thì không thấy nút nào của chủ phiếu.
        $nguoiKhac = $this->chay([$this->phieu(['status' => 'draft'])], uid: 999)['advanceRows'][0];
        $this->assertFalse($nguoiKhac->canSubmit);
        $this->assertFalse($nguoiKhac->canCreateSettlement);

        $daChi = $this->chay([$this->phieu(['status' => 'accounting_approved'])])['advanceRows'][0];
        $this->assertTrue($daChi->canCreateSettlement, 'đã chi và chưa có hồ sơ hoàn ứng');

        $daCoHoSo = $this->chay([$this->phieu([
            'status' => 'accounting_approved',
            'settlementRequest' => (object) ['id' => 9, 'status' => 'draft', 'settlement_type' => 'balanced', 'actual_amount' => 0, 'refund_amount' => 0, 'difference_amount' => 0],
        ])])['advanceRows'][0];
        $this->assertFalse($daCoHoSo->canCreateSettlement, 'đã có hồ sơ thì không hiện nút lập nữa');

        // Quyền duyệt: `pending` là mã cũ, vẫn phải hiện nút.
        foreach (['submitted', 'pending'] as $st) {
            $this->assertTrue($this->chay([$this->phieu(['status' => $st])], ql: true)['advanceRows'][0]->canManagementApprove, $st);
        }
        $this->assertTrue($this->chay([$this->phieu(['status' => 'admin_approved'])], kt: true)['advanceRows'][0]->canAccountingApprove);
        $this->assertFalse($this->chay([$this->phieu(['status' => 'admin_approved'])])['advanceRows'][0]->canAccountingApprove, 'không có quyền thì không có nút');

        $hoSo = fn (string $ss) => $this->phieu([
            'status' => 'accounting_approved',
            'settlementRequest' => (object) ['id' => 42, 'status' => $ss, 'settlement_type' => 'balanced', 'actual_amount' => 0, 'refund_amount' => 0, 'difference_amount' => 0],
        ]);
        $this->assertTrue($this->chay([$hoSo('submitted')], ql: true)['advanceRows'][0]->canApproveSettlement);
        $this->assertTrue($this->chay([$hoSo('admin_approved')], kt: true)['advanceRows'][0]->canReconcileSettlement);
        $this->assertSame(42, $this->chay([$hoSo('submitted')], ql: true)['advanceRows'][0]->settlementId);
    }

    public function test_cat_chuoi_va_dinh_dang_giu_dung_ban_cu(): void
    {
        $row = $this->chay([$this->phieu([
            'reason' => str_repeat('a', 200),
            'note' => str_repeat('b', 200),
        ])])['advanceRows'][0];

        $this->assertSame(82 + 3, mb_strlen($row->reasonText), 'Str::limit 82 ký tự + dấu ba chấm');
        $this->assertSame(60 + 3, mb_strlen($row->noteText), 'Str::limit 60 ký tự + dấu ba chấm');
        $this->assertSame('12.500.000 đ', $row->amountText);
        $this->assertSame('10/09/2026 08:30', $row->createdAtText);
        $this->assertSame('15/10/2026', $row->dueDateText);

        $khongGhiChu = $this->chay([$this->phieu(['note' => null])])['advanceRows'][0];
        $this->assertSame('', $khongGhiChu->noteText, 'không ghi chú thì chuỗi rỗng, view không in dòng phụ');

        $khongNguoiTao = $this->chay([$this->phieu(['creator' => null])])['advanceRows'][0];
        $this->assertSame('—', $khongNguoiTao->creatorName);
    }

    public function test_o_chi_so_va_dong_goi_y_hoan_ung(): void
    {
        $co = (new AdvanceRequestListPresenter)->viewData(
            items: [], labels: self::LABELS, currentUserId: 1,
            canApproveManagement: false, canApproveAccounting: false,
            today: Carbon::parse('2026-09-30')->startOfDay(),
            stats: ['total' => 1234, 'pending' => 5, 'need_settlement' => 3, 'done' => 2, 'overdue_settlement' => 7],
            pageCount: 20, totalCount: 1234, rawFilters: [], oldInput: [], currentUserName: 'A',
        )['kpi'];

        // Dấu phân cách tiếng Việt: bản cũ dùng `number_format()` trơn (`1,234`).
        $this->assertSame('1.234', $co->total);
        $this->assertTrue($co->hasOverdue);
        $this->assertSame('7 phiếu đã quá hạn', $co->settlementHint);

        $khong = (new AdvanceRequestListPresenter)->viewData(
            items: [], labels: self::LABELS, currentUserId: 1,
            canApproveManagement: false, canApproveAccounting: false,
            today: Carbon::parse('2026-09-30')->startOfDay(),
            stats: ['overdue_settlement' => 0],
            pageCount: 0, totalCount: 0, rawFilters: [], oldInput: [], currentUserName: 'A',
        )['kpi'];

        $this->assertFalse($khong->hasOverdue);
        // Khoảng trắng hai đầu giữ y bản cũ (`@else đã chi tiền, … @endif`).
        $this->assertSame(' đã chi tiền, chờ nhân sự quyết toán ', $khong->settlementHint);
    }

    public function test_bieu_mau_uu_tien_old_input_roi_moi_den_ten_nguoi_dung(): void
    {
        $khongOld = (new AdvanceRequestListPresenter)->viewData(
            items: [], labels: self::LABELS, currentUserId: 1,
            canApproveManagement: false, canApproveAccounting: false,
            today: Carbon::parse('2026-09-30')->startOfDay(),
            stats: [], pageCount: 0, totalCount: 0, rawFilters: [], oldInput: [],
            currentUserName: 'Nguyễn Đăng Nhập',
        )['createForm'];

        $this->assertSame('Nguyễn Đăng Nhập', $khongOld['recipient_name'], 'mặc định là tên người đang đăng nhập');
        $this->assertSame('', $khongOld['amount']);

        $coOld = (new AdvanceRequestListPresenter)->viewData(
            items: [], labels: self::LABELS, currentUserId: 1,
            canApproveManagement: false, canApproveAccounting: false,
            today: Carbon::parse('2026-09-30')->startOfDay(),
            stats: [], pageCount: 0, totalCount: 0, rawFilters: [],
            oldInput: ['recipient_name' => 'Tên vừa gõ', 'amount' => '999'],
            currentUserName: 'Nguyễn Đăng Nhập',
        )['createForm'];

        $this->assertSame('Tên vừa gõ', $coOld['recipient_name']);
        $this->assertSame('999', $coOld['amount']);
    }
}
