<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Repositories\PaymentRequestRepositoryInterface;
use App\Repositories\SupplierDebtRepository;
use Illuminate\Support\Facades\DB;

/**
 * Xoá một ĐNTT cùng mọi liên kết của nó.
 *
 * Trước đây thân hàm này được chép NGUYÊN VĂN ba lần trong `routes/finance.php`
 * (`xoa-full-thao`, `thao_destroy_any_status`, và một biến thể ở
 * `force-delete-by-thao`) — sửa một chỗ quên hai chỗ kia là chuyện sớm muộn.
 *
 * Thứ tự bắt buộc, đặt trong một giao dịch:
 *   1. Gỡ các đợt thanh toán công nợ khỏi phiếu (tiền chưa chi thì trả về
 *      `planned`), nếu không sẽ còn đợt trỏ tới phiếu đã biến mất.
 *   2. Xoá bản ghi con (đính kèm, lịch sử duyệt, log).
 *   3. Xoá chính phiếu.
 */
final class PaymentRequestEraser
{
    public function __construct(
        private readonly PaymentRequestRepositoryInterface $paymentRequests,
        private readonly SupplierDebtRepository $debts,
    ) {}

    /**
     * Xoá phiếu, KHÔNG tính lại số dư công nợ.
     *
     * Dùng cho các endpoint xoá thường — giữ đúng hành vi cũ.
     */
    public function erase(int $paymentRequestId): void
    {
        DB::transaction(function () use ($paymentRequestId): void {
            $this->debts->unlinkPaymentRequest($paymentRequestId);
            $this->paymentRequests->deleteChildRows($paymentRequestId);
            $this->paymentRequests->delete($paymentRequestId);
        });
    }

    /**
     * Xoá phiếu RỒI tính lại số dư của những công nợ bị ảnh hưởng.
     *
     * Khác biệt duy nhất của `force-delete-by-thao`: sau khi gỡ liên kết thì
     * `paid_amount`/`status` của công nợ phải được tính lại, nếu không công nợ
     * vẫn hiện "đã trả" bằng tiền của một phiếu không còn tồn tại.
     *
     * Lưu ý thứ tự: phải lấy danh sách công nợ liên quan TRƯỚC khi gỡ liên kết,
     * vì gỡ xong thì không còn tra ngược được nữa.
     *
     * ⚠️ CỐ Ý KHÔNG xoá bản ghi con ở đây. Bản gốc trong `routes/finance.php`
     * không xoá, nên hai đường xoá của hệ thống đang lệch nhau: `erase()` dọn
     * tệp đính kèm, đường này để lại. Đó là điểm KHÔNG NHẤT QUÁN có sẵn, không
     * phải chủ ý thiết kế — nhưng gộp lại là XOÁ THÊM DỮ LIỆU nên phải do chủ
     * hệ thống quyết, không tự ý làm trong một đợt dọn code.
     */
    public function eraseAndRebalanceDebts(int $paymentRequestId, SupplierDebtBalanceCalculator $calculator): void
    {
        $affectedDebtIds = $this->debts->debtIdsLinkedTo($paymentRequestId);

        // Bản gốc không bọc giao dịch; bọc vào chỉ đổi hành vi khi GẶP LỖI
        // giữa chừng (không còn xoá nửa vời), không đổi kết quả khi chạy trót lọt.
        DB::transaction(function () use ($paymentRequestId): void {
            $this->debts->unlinkPaymentRequest($paymentRequestId);
            $this->paymentRequests->delete($paymentRequestId);
        });

        $calculator->recalculate($affectedDebtIds);
    }
}
