<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\SettlementDetailPresenter;
use Tests\TestCase;

/**
 * `SettlementDetailPresenter` — thay 2 khối `@php` của `settlement_requests/show` (2026-09-30).
 */
final class SettlementDetailPresenterTest extends TestCase
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
            'id' => 5, 'code' => 'ST-0005', 'created_by' => 77, 'company' => 'EGO',
            'recipient_name' => 'Nguyễn A', 'advance_amount' => '12500000.00',
            'actual_amount' => '11000000.00', 'refund_amount' => '1500000.00',
            'difference_amount' => '-1500000.00', 'settlement_type' => 'refund',
            'reason' => 'Quyết toán', 'note' => 'ghi chú', 'status' => 'draft',
            'created_at' => '2026-09-20 08:30:00', 'submitted_at' => null,
            'admin_approved_at' => null, 'accounting_approved_at' => null,
            'admin_approved_by' => null, 'accounting_approved_by' => null,
            'attachments' => [], 'creator' => (object) ['name' => 'Người Tạo'],
            'advanceRequest' => (object) ['id' => 900, 'code' => 'AR-0900'],
        ], $ghiDe);
    }

    /** @param array<int|string, string> $approvers */
    private function chay(array $ghiDe = [], array $approvers = [], ?int $uid = 77, bool $viewAll = false, bool $ql = false, bool $kt = false): object
    {
        return (new SettlementDetailPresenter)->viewData(
            item: $this->phieu($ghiDe), labels: self::LABELS, approvers: $approvers,
            currentUserId: $uid, canViewAll: $viewAll,
            canApproveManagement: $ql, canApproveAccounting: $kt,
        )['detail'];
    }

    public function test_dinh_dang_thoi_diem_va_tien(): void
    {
        $d = $this->chay(['submitted_at' => '2026-09-21 09:05:00']);

        $this->assertSame('20/09/2026 08:30', $d->createdAtText);
        $this->assertSame('21/09/2026 09:05', $d->submittedAtText);
        // Gạch NGẮN `-` khi trống, KHÁC gạch dài `—` của DisplayFormat::date().
        $this->assertSame('-', $d->adminApprovedAtText);
        $this->assertSame('-', $d->accountingApprovedAtText);
        $this->assertSame('12.500.000 đ', $d->advanceAmountText);
        $this->assertSame('11.000.000 đ', $d->actualAmountText);
    }

    public function test_ten_nguoi_duyet_ba_nhanh(): void
    {
        $this->assertSame('-', $this->chay()->adminApproverName, 'chưa ai duyệt');

        $co = $this->chay(['admin_approved_by' => 9], [9 => 'Trần Quản Lý']);
        $this->assertSame('Trần Quản Lý', $co->adminApproverName);

        // Id không còn trong bảng users → `#<id>`, đúng bản cũ.
        $mat = $this->chay(['accounting_approved_by' => 404], []);
        $this->assertSame('#404', $mat->accountingApproverName);
    }

    public function test_ket_qua_doi_soat_va_nhan(): void
    {
        $refund = $this->chay(['settlement_type' => 'refund']);
        $this->assertSame('Nhân sự hoàn lại công ty', $refund->settlementTypeText);
        $this->assertSame('Số tiền hoàn lại', $refund->resultLabel);
        $this->assertSame('1.500.000 đ', $refund->resultAmountText);
        $this->assertTrue($refund->hasResultAmount);

        $payMore = $this->chay(['settlement_type' => 'pay_more', 'difference_amount' => '2000000.00']);
        $this->assertSame('Công ty thanh toán thêm', $payMore->settlementTypeText);
        $this->assertSame('2.000.000 đ', $payMore->resultAmountText);
        $this->assertSame('2.000.000 đ', $payMore->payMoreAmountText);

        // `max(0, …)` của bản cũ: chênh lệch ÂM ở nhánh pay_more thì ra 0.
        $am = $this->chay(['settlement_type' => 'pay_more', 'difference_amount' => '-500000.00']);
        $this->assertSame('0 đ', $am->payMoreAmountText);
        $this->assertFalse($am->hasResultAmount, 'bản cũ: `@if($resultAmount > 0)` nên 0 thì không in');

        $balanced = $this->chay(['settlement_type' => 'balanced', 'refund_amount' => 0]);
        $this->assertSame('Khớp đủ', $balanced->settlementTypeText);
        $this->assertSame('Chênh lệch', $balanced->resultLabel);
        $this->assertFalse($balanced->hasResultAmount);
    }

    public function test_chung_tu_nhan_dung_loai(): void
    {
        $d = $this->chay(['attachments' => [
            'settlements/hoa-don.pdf', 'settlements/anh.JPG', 'settlements/khong-duoi', '', 'x/b.xlsx',
        ]]);

        // Bản cũ: `array_filter` bỏ phần tử rỗng trước khi đếm và hiển thị.
        $this->assertCount(4, $d->attachments);
        $this->assertSame('4', $d->attachmentCountText);

        $this->assertSame(['hoa-don.pdf', 'PDF'], [$d->attachments[0]->name, $d->attachments[0]->extLabel]);
        $this->assertSame('ẢNH', $d->attachments[1]->extLabel, 'đuôi ảnh viết HOA vẫn phải nhận ra');
        $this->assertSame('FILE', $d->attachments[2]->extLabel, 'không có phần mở rộng thì là FILE');
        $this->assertSame('XLSX', $d->attachments[3]->extLabel);
    }

    public function test_hai_chuoi_mac_dinh_cua_cong_ty_khac_nhau(): void
    {
        $d = $this->chay(['company' => null]);

        // Bản cũ dùng HAI chuỗi mặc định khác nhau ở hai chỗ — giữ y nguyên.
        $this->assertSame('Chưa có công ty', $d->companyHeader);
        $this->assertSame('-', $d->companyField);
    }

    public function test_co_quyen_va_luong_duyet(): void
    {
        $nhap = $this->chay(['status' => 'draft']);
        $this->assertTrue($nhap->canSubmit);
        $this->assertTrue($nhap->canDelete, 'chủ phiếu xoá được bản nháp');
        $this->assertTrue($nhap->hasAnyAction);
        $this->assertSame('', $nhap->step2State);
        $this->assertSame('Chưa đến bước duyệt', $nhap->step2Text);
        // 🚨 Bản cũ in RỖNG ở đây vì `@else` dính ngay sau chữ "toán" nên Blade không biên dịch.
        $this->assertSame('Chưa đến bước kế toán', $nhap->step3Text);

        $daGui = $this->chay(['status' => 'submitted'], ql: true);
        $this->assertSame('active', $daGui->step2State);
        $this->assertSame('Đang chờ xử lý', $daGui->step2Text);
        $this->assertTrue($daGui->canAdminAction);
        $this->assertFalse($daGui->canSubmit);

        $choKeToan = $this->chay(['status' => 'admin_approved', 'admin_approved_by' => 9], [9 => 'Trần QL'], kt: true);
        $this->assertSame('done', $choKeToan->step2State);
        $this->assertSame('Trần QL', $choKeToan->step2Text);
        $this->assertSame('active', $choKeToan->step3State);
        $this->assertSame('Đang chờ kế toán', $choKeToan->step3Text);
        $this->assertTrue($choKeToan->canAccAction);

        $xong = $this->chay(['status' => 'accounting_approved', 'accounting_approved_by' => 4], [4 => 'Kế Toán A']);
        $this->assertSame('done', $xong->step3State);
        $this->assertSame('Kế Toán A', $xong->step3Text);
        $this->assertFalse($xong->hasAnyAction, 'xong rồi thì không còn nút nào cho chủ phiếu');

        // Người xem-tất-cả xoá được ở mọi trạng thái.
        $this->assertTrue($this->chay(['status' => 'accounting_approved'], viewAll: true)->canDelete);
    }

    public function test_nguoi_tao_va_nguoi_tam_ung(): void
    {
        $this->assertSame('Người Tạo', $this->chay()->creatorDisplay);
        $this->assertSame('Nguyễn A', $this->chay()->recipientText);

        // Không có creator → rơi về recipient_name; không có cả hai → `#<id>`.
        $this->assertSame('Nguyễn A', $this->chay(['creator' => null])->creatorDisplay);
        $this->assertSame('#77', $this->chay(['creator' => null, 'recipient_name' => null])->creatorDisplay);
        $this->assertSame('-', $this->chay(['creator' => null, 'recipient_name' => ''])->recipientText);
    }

    public function test_trang_thai_va_slug(): void
    {
        $this->assertSame('Chờ kế toán đối soát', $this->chay(['status' => 'admin_approved'])->statusLabel);
        $this->assertSame('admin-approved', $this->chay(['status' => 'admin_approved'])->statusSlug,
            'lớp CSS dùng gạch NGANG');
        $this->assertSame('la', $this->chay(['status' => 'la'])->statusLabel, 'không có nhãn thì in nguyên mã');
        $this->assertSame('draft', $this->chay(['status' => ''])->statusSlug, 'trạng thái rỗng vẫn ra draft');
        $this->assertSame('-', $this->chay(['status' => ''])->statusLabel);
    }
}
