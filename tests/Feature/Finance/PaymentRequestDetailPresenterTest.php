<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\PaymentRequestDetailPresenter;
use Tests\TestCase;

/**
 * `PaymentRequestDetailPresenter` — thay 2 khối `@php` của `payment_requests/show` (2026-09-30).
 */
final class PaymentRequestDetailPresenterTest extends TestCase
{
    /** @param array<string, mixed> $ghiDe */
    private function phieu(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 5, 'code' => 'PR-0005', 'created_by' => 77, 'status' => 'draft',
            'doc_type' => 'payment_voucher', 'receiver_name' => 'Nguyễn A', 'department' => 'Kỹ thuật',
            'company' => 'EGO', 'amount' => 12500000, 'payment_due_date' => '2026-10-15',
            'payment_content' => 'Nội dung', 'reason' => 'Lý do',
            'bank_name' => 'VCB', 'bank_account' => '001', 'bank_account_name' => 'NGUYEN A',
            'admin_note' => null, 'accounting_note' => null,
            'created_at' => '2026-09-20 08:30:00',
            'admin_approved_by' => null, 'admin_approved_at' => null,
            'accounting_approved_by' => null, 'accounting_approved_at' => null,
            'creator' => (object) ['name' => 'Người Tạo'],
            'director' => null, 'accountant' => null,
        ], $ghiDe);
    }

    /** @param iterable<object> $atts */
    private function chay(array $ghiDe = [], iterable $atts = [], ?int $uid = 77, bool $isAdmin = false, bool $isAcc = false, ?bool $policy = null): object
    {
        return (new PaymentRequestDetailPresenter)->viewData(
            item: $this->phieu($ghiDe),
            attachments: $atts,
            currentUserId: $uid,
            isAdmin: $isAdmin,
            isAccounting: $isAcc,
            canEditByPolicy: $policy,
            actionUrls: [
                'approveAdmin' => '/a/approve', 'rejectAdmin' => '/a/reject',
                'approveAcc' => '/k/approve', 'rejectAcc' => '/k/reject',
            ],
            fileUrls: fn (int $id): array => ['download' => "/tai/$id", 'preview' => "/xem/$id"],
        )['detail'];
    }

    public function test_dinh_dang_tien_theo_dau_tieng_viet(): void
    {
        // ĐỔI ĐẦU RA có chủ ý: bản cũ `number_format()` trơn ra `12,500,000 đ` (dấu Anh).
        $this->assertSame('12.500.000 đ', $this->chay()->amountText);
        // Bản cũ ép `(int)` TRƯỚC khi định dạng — phần thập phân bị cắt, giữ y.
        $this->assertSame('12.500.000 đ', $this->chay(['amount' => 12500000.99])->amountText);
        $this->assertSame('0 đ', $this->chay(['amount' => 0])->amountText);
    }

    public function test_ten_nguoi_duyet_lay_tu_quan_he_dung_ten(): void
    {
        // 🚨 View cũ gọi `$item->adminApprover` — quan hệ KHÔNG tồn tại (tên đúng là `director`),
        // nên Eloquent trả null và trang LUÔN in `#<id>`. Nay lấy được tên thật.
        $co = $this->chay([
            'status' => 'admin_approved',
            'admin_approved_by' => 9, 'admin_approved_at' => '2026-09-21 10:00:00',
            'director' => (object) ['name' => 'Trần Quản Lý'],
        ]);
        $this->assertSame('Trần Quản Lý', $co->adminApproverName);
        $this->assertSame('Trần Quản Lý', $co->step2Text);
        $this->assertSame('21/09/2026 10:00', $co->adminApprovedAtText);

        // Quan hệ chưa nạp mà có id → nhánh dự phòng `#<id>`; không có id → `-`.
        $this->assertSame('#9', $this->chay(['admin_approved_by' => 9])->adminApproverName);
        $this->assertSame('-', $this->chay()->adminApproverName);
    }

    public function test_nhan_loai_phieu_bon_nhanh(): void
    {
        $this->assertSame('Phiếu chi', $this->chay(['doc_type' => 'payment_voucher'])->docTypeLabel);
        $this->assertSame('Đề nghị hoàn tiền', $this->chay(['doc_type' => 'refund_request'])->docTypeLabel);
        $this->assertSame('Tạm ứng', $this->chay(['doc_type' => 'advance'])->docTypeLabel);
        $this->assertSame('Phiếu đề nghị thanh toán', $this->chay(['doc_type' => 'loai_la'])->docTypeLabel);
    }

    public function test_co_quyen_va_policy_chi_de_quyen_sua(): void
    {
        foreach (['draft', 'admin_rejected', 'accounting_rejected'] as $st) {
            $d = $this->chay(['status' => $st]);
            $this->assertTrue($d->canSubmit, $st);
            $this->assertTrue($d->canDelete, $st);
            $this->assertTrue($d->canEdit, $st);
        }

        $daGui = $this->chay(['status' => 'submitted']);
        $this->assertFalse($daGui->canSubmit);
        $this->assertFalse($daGui->canEdit);

        // Policy CHỈ đè quyền sửa; gửi duyệt và xoá vẫn theo trạng thái + chủ phiếu.
        $policyMo = $this->chay(['status' => 'submitted'], policy: true);
        $this->assertTrue($policyMo->canEdit, 'policy cho sửa');
        $this->assertFalse($policyMo->canSubmit, 'nhưng không mở luôn quyền gửi duyệt');

        $policyDong = $this->chay(['status' => 'draft'], policy: false);
        $this->assertFalse($policyDong->canEdit);
        $this->assertTrue($policyDong->canSubmit);

        $this->assertFalse($this->chay(['status' => 'draft'], uid: 999)->canSubmit, 'người khác');
    }

    public function test_khoi_xu_ly_doi_theo_vai(): void
    {
        $qltc = $this->chay(['status' => 'submitted'], isAdmin: true);
        $this->assertTrue($qltc->canAdminAction);
        $this->assertSame('Quản lý tài chính xử lý', $qltc->actionTitle);
        $this->assertSame('/a/approve', $qltc->approveUrl);
        $this->assertSame('/a/reject', $qltc->rejectUrl);
        $this->assertSame('Duyệt phiếu này?', $qltc->approveConfirm);
        $this->assertSame('Duyệt phiếu', $qltc->approveButtonLabel);

        $keToan = $this->chay(['status' => 'admin_approved'], isAcc: true);
        $this->assertTrue($keToan->canAccAction);
        $this->assertSame('Kế toán xử lý', $keToan->actionTitle);
        $this->assertSame('/k/approve', $keToan->approveUrl);
        $this->assertSame('Xác nhận đã chi phiếu này?', $keToan->approveConfirm);
        $this->assertSame('Xác nhận đã chi', $keToan->approveButtonLabel);

        $chuPhieu = $this->chay(['status' => 'draft']);
        $this->assertSame('Thao tác phiếu', $chuPhieu->actionTitle);
    }

    public function test_the_chung_tu(): void
    {
        $d = $this->chay(atts: [
            (object) ['id' => 11, 'original_name' => 'hoa-don.pdf', 'path' => 'a/b.pdf', 'mime_type' => 'application/pdf', 'size' => 204800],
            (object) ['id' => 12, 'original_name' => 'anh.JPG', 'path' => 'a/c.jpg', 'mime_type' => 'image/jpeg', 'size' => null],
            (object) ['id' => 13, 'original_name' => null, 'path' => 'a/khong-duoi', 'mime_type' => null, 'size' => 1024],
        ]);

        $this->assertSame('PDF', $d->attachments[0]->badge);
        // `number_format($x, 1)` MẶC ĐỊNH → dấu CHẤM thập phân kiểu Anh, giữ y bản cũ.
        $this->assertSame('200.0 KB', $d->attachments[0]->sizeText);
        $this->assertSame('/tai/11', $d->attachments[0]->downloadUrl);
        $this->assertSame('/xem/11', $d->attachments[0]->previewUrl);

        $this->assertSame('ẢNH', $d->attachments[1]->badge, 'đuôi .JPG viết HOA vẫn nhận ra');
        $this->assertSame('', $d->attachments[1]->sizeText, 'không có size thì không in gì');

        $this->assertSame('khong-duoi', $d->attachments[2]->name, 'không có tên gốc thì lấy basename');
        $this->assertSame('FILE', $d->attachments[2]->badge);
        $this->assertSame('Tệp đính kèm', $d->attachments[2]->mimeText);
    }

    public function test_luong_duyet_ba_buoc(): void
    {
        $nhap = $this->chay(['status' => 'draft']);
        $this->assertSame('', $nhap->step2State);
        $this->assertSame('Chưa đến bước duyệt', $nhap->step2Text);
        $this->assertSame('Chưa đến bước kế toán', $nhap->step3Text);

        $daGui = $this->chay(['status' => 'submitted']);
        $this->assertSame('active', $daGui->step2State);
        $this->assertSame('Đang chờ xử lý', $daGui->step2Text);

        $daChi = $this->chay([
            'status' => 'accounting_approved',
            'accounting_approved_by' => 4, 'accounting_approved_at' => '2026-09-22 11:00:00',
            'accountant' => (object) ['name' => 'Kế Toán A'],
        ]);
        $this->assertSame('done', $daChi->step3State);
        $this->assertSame('Kế Toán A', $daChi->step3Text);
        $this->assertTrue($daChi->showPdf, 'chỉ phiếu đã chi mới có nút PDF');
        $this->assertFalse($this->chay(['status' => 'draft'])->showPdf);
    }

    public function test_gia_tri_trong_ra_gach_ngan(): void
    {
        $d = $this->chay([
            'receiver_name' => '', 'department' => null, 'company' => null,
            'bank_name' => null, 'bank_account' => '', 'bank_account_name' => null,
            'payment_content' => null, 'reason' => '', 'payment_due_date' => null,
            'admin_note' => null, 'accounting_note' => '',
        ]);

        foreach (['receiverName', 'department', 'companyField', 'bankName', 'bankAccount',
            'bankAccountName', 'paymentContent', 'reason', 'adminNote', 'accountingNote',
            'paymentDueDateText'] as $truong) {
            $this->assertSame('-', $d->{$truong}, $truong);
        }
        // Riêng ô ở đầu trang dùng chuỗi KHÁC — giữ y bản cũ.
        $this->assertSame('Chưa có công ty', $d->companyHeader);
    }
}
