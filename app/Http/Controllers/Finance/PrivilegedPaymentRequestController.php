<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Contracts\Repositories\PaymentRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Finance\FinanceFullAccess;
use App\Services\Finance\PaymentRequestEraser;
use App\Services\Finance\SupplierDebtBalanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Xoá ĐNTT ở MỌI trạng thái — dành riêng cho người có đặc quyền tài chính.
 *
 * ## Trước đây code này nằm ở đâu
 * Hai endpoint dưới đây từng là closure viết thẳng trong `routes/finance.php`,
 * trong đó thân hàm xoá bị chép nguyên văn ba lần.
 *
 * ## Vì sao ở đây KHÔNG có update()/destroy()
 * Từng có, và chúng CHƯA BAO GIỜ CHẠY. `routes/finance.php` đăng ký chúng trên
 * `PATCH|PUT` và `POST|DELETE /payment-requests/{id}`, nhưng phía dưới file lại
 * đăng ký PUT/DELETE cùng URI trỏ vào PaymentRequestController — mà với
 * method+URI trùng nhau, Laravel để bản đăng ký SAU ghi đè bản trước
 * (`RouteCollection::addToCollections()` ghi vào mảng theo khoá method+URI).
 *
 * Quan trọng: tính năng "người có đặc quyền sửa/xoá phiếu ĐÃ kế toán duyệt"
 * KHÔNG hề mất — nó nằm trong {@see PaymentRequestController::update()} và
 * `destroy()`, hai chỗ đó tự kiểm `canEditCompletedFinanceRecord()`. Bản ở đây
 * chỉ là bản THỪA, lại còn ghi thẳng mọi cột từ request mà không kiểm tra dữ
 * liệu, tức kém an toàn hơn bản đang chạy.
 *
 * Đã xoá 2026-08-06 theo quyết định của chủ hệ thống. Nếu định thêm lại: đọc
 * PrivilegedPaymentRequestCharacterizationTest trước — nó khoá đúng hành vi hiện
 * hành để không ai vô tình đổi phân quyền sửa phiếu tài chính.
 */
final class PrivilegedPaymentRequestController extends Controller
{
    public function __construct(
        private readonly PaymentRequestRepositoryInterface $paymentRequests,
        private readonly FinanceFullAccess $access,
        private readonly PaymentRequestEraser $eraser,
    ) {}

    /**
     * Endpoint xoá đặc quyền riêng (`DELETE .../xoa-full-thao`).
     *
     * Chặn thẳng 403 với người không có đặc quyền.
     *
     * Không nơi nào trong giao diện gọi tới — đó là CHỦ Ý: đây là lối thoát
     * hiểm để xoá tay một phiếu khi giao diện không cho. Chủ hệ thống đã xác
     * nhận giữ lại (2026-08-06), đừng đề xuất xoá.
     */
    public function eraseCompletely(Request $request, int $id): RedirectResponse
    {
        abort_unless($this->access->allows($request->user()), 403);

        return $this->eraseAndRedirect($id);
    }

    /**
     * Xoá phiếu RỒI tính lại số dư công nợ nhà cung cấp.
     *
     * Đây là endpoint đang được dùng thật, gọi từ
     * `resources/views/payment-requests/_buibichthao_actions.blade.php`.
     */
    public function forceDestroy(
        Request $request,
        int $id,
        SupplierDebtBalanceCalculator $calculator,
    ): RedirectResponse {
        abort_unless($this->access->allows($request->user()), 403);
        abort_if($this->paymentRequests->find($id) === null, 404);

        $this->eraser->eraseAndRebalanceDebts($id, $calculator);

        return redirect('/payment-requests')->with('success', 'Đã xóa phiếu ĐNTT #'.$id.'.');
    }

    /**
     * Xoá phiếu và quay về danh sách — phần chung của hai đường xoá.
     */
    private function eraseAndRedirect(int $id): RedirectResponse
    {
        abort_if($this->paymentRequests->find($id) === null, 404);

        $this->eraser->erase($id);

        return redirect('/payment-requests')
            ->with('success', $this->actorName().' đã xóa ĐNTT #'.$id.' ở mọi trạng thái.');
    }

    /**
     * Tên hiển thị trong thông báo.
     *
     * Thông báo cũ viết cứng "Bùi Bích Thảo". Lấy theo tên người đang đăng nhập
     * để khi đổi người có đặc quyền (qua config) thì thông báo không nói sai tên.
     */
    private function actorName(): string
    {
        return (string) (auth()->user()->name ?? 'Người có đặc quyền');
    }
}
