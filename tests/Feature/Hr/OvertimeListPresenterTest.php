<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\OvertimeRequest;
use App\Models\User;
use App\View\Presenters\Hr\OvertimeListPresenter;
use Tests\TestCase;

/** `OvertimeListPresenter` — assert GIÁ TRỊ THẬT, không dừng ở "chạy không lỗi". */
final class OvertimeListPresenterTest extends TestCase
{
    private function don(array $attrs = []): OvertimeRequest
    {
        $don = (new OvertimeRequest)->forceFill(array_merge([
            'id' => 1, 'user_id' => 11, 'approver_id' => 22, 'overtime_date' => '2026-10-12',
            'start_at' => '2026-10-12 18:00:00', 'end_at' => '2026-10-12 20:30:00',
            'hours' => '2.50', 'status' => 'pending', 'reason' => 'Chạy deadline', 'approval_note' => null,
        ], $attrs));

        $nv = (new User)->forceFill(['id' => 11, 'name' => 'Nhân viên A']);
        $nv->setRelation('department', (object) ['name' => 'Phòng Kỹ thuật']);
        $don->setRelation('user', $nv);
        $don->setRelation('approver', (new User)->forceFill(['id' => 22, 'name' => 'Người duyệt']));

        return $don;
    }

    public function test_so_gio_dung_dau_tieng_viet_va_cat_so_0(): void
    {
        $p = new OvertimeListPresenter;

        $gio = function (mixed $v) use ($p): string {
            $d = $p->viewData([], ['hours' => $v], 0, false);

            return $d['summaryCards']->hoursText;
        };

        // Nguyên thì không có phần thập phân; lẻ thì dấu PHẨY; nghìn thì dấu CHẤM.
        $this->assertSame('0', $gio(0));
        $this->assertSame('3', $gio(3.0));
        $this->assertSame('2,5', $gio(2.5));
        $this->assertSame('1,25', $gio(1.25));
        $this->assertSame('1.234,5', $gio(1234.5));
        $this->assertSame('7', $gio('7.00'));
    }

    public function test_o_chi_so_dung_dau_phan_cach_tieng_viet(): void
    {
        $cards = (new OvertimeListPresenter)->viewData([], [
            'total' => 1234, 'pending' => 0, 'approved' => 12, 'rejected' => 7, 'hours' => 0,
        ], 0, false)['summaryCards'];

        $this->assertSame('1.234', $cards->totalText, 'bản cũ in 1,234 kiểu Anh — đổi có chủ ý');
        $this->assertSame('0', $cards->pendingText);
        $this->assertSame('12', $cards->approvedText);
        $this->assertSame('7', $cards->rejectedText);
    }

    public function test_dong_don_in_dung_gia_tri(): void
    {
        $row = (new OvertimeListPresenter)->viewData([$this->don()], [], 999, false)['overtimeRows'][0];

        $this->assertSame(1, $row->id);
        $this->assertSame('Nhân viên A', $row->userName);
        $this->assertSame('Phòng Kỹ thuật', $row->departmentName);
        $this->assertSame('12/10/2026', $row->dateText);
        $this->assertSame('18:00 - 20:30', $row->timeText);
        $this->assertSame('2,5', $row->hoursText);
        $this->assertSame('Người duyệt', $row->approverName);
        $this->assertSame('Chạy deadline', $row->reasonText);
        $this->assertSame('Chờ duyệt', $row->statusLabel);
        $this->assertSame('warning', $row->statusTone);
        $this->assertSame('', $row->approvalNote);
    }

    public function test_gia_tri_du_phong_giu_dung_gach_ngan_cua_ban_cu(): void
    {
        $don = $this->don(['reason' => '']);
        $don->setRelation('user', null);
        $don->setRelation('approver', null);

        $row = (new OvertimeListPresenter)->viewData([$don], [], 0, false)['overtimeRows'][0];

        // Bản cũ: `?? '-'` (gạch NGẮN, không phải `—` của DisplayFormat).
        $this->assertSame('-', $row->userName);
        $this->assertSame('-', $row->departmentName);
        // Lý do là chuỗi RỖNG cũng ra `-` vì bản cũ dùng `?:` chứ không `??`.
        $this->assertSame('-', $row->reasonText);
        $this->assertSame('HR / Admin', $row->approverName);
    }

    public function test_quyen_duyet_dung_bon_truong_hop(): void
    {
        $p = new OvertimeListPresenter;

        // Người duyệt được chỉ định (id 22) → được duyệt dù không phải quản lý.
        $this->assertTrue($p->viewData([$this->don()], [], 22, false)['overtimeRows'][0]->canApprove);
        // Người khác, không quản lý → không được.
        $this->assertFalse($p->viewData([$this->don()], [], 33, false)['overtimeRows'][0]->canApprove);
        // Quản lý HR → được, kể cả không phải người duyệt.
        $this->assertTrue($p->viewData([$this->don()], [], 33, true)['overtimeRows'][0]->canApprove);
        // Đơn KHÔNG còn chờ duyệt → không ai duyệt nữa (bản cũ kiểm ở view bằng `@if`).
        $this->assertFalse($p->viewData([$this->don(['status' => 'approved'])], [], 22, true)['overtimeRows'][0]->canApprove);
    }

    public function test_trang_thai_la_roi_ve_tong_secondary(): void
    {
        $row = (new OvertimeListPresenter)->viewData([$this->don(['status' => 'cancelled'])], [], 0, true)['overtimeRows'][0];

        $this->assertSame('secondary', $row->statusTone);
        $this->assertSame('Cancelled', $row->statusLabel, 'model dùng ucfirst cho trạng thái lạ');
    }
}
