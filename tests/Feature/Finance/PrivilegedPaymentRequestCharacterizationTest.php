<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Chốt hành vi sửa/xoá ĐNTT của người có ĐẶC QUYỀN tài chính.
 *
 * Logic này vốn là ~420 dòng closure trong `routes/finance.php`; test viết ra
 * TRƯỚC khi tách để việc tách chỉ được dời code, không được đổi kết quả.
 *
 * Các đường đang có:
 *  1. PUT    /payment-requests/{id}                        sửa — PaymentRequestController
 *  2. DELETE /payment-requests/{id}                        xoá — PaymentRequestController
 *  3. DELETE /payment-requests/{id}/xoa-full-thao          xoá — lối thoát hiểm
 *  4. POST   /payment-requests/{id}/force-delete-by-thao   xoá + tính lại công nợ
 *  5. POST   /payment-requests/{id}/ban-sao                sao chép phiếu
 *
 * Đặc quyền hiện xác định theo EMAIL (`config('ego.finance_full_access_emails')`)
 * chứ không theo vai trò — test giữ nguyên sự thật đó thay vì giả vờ hệ thống
 * làm khác.
 *
 * ⚠️ 2026-08-06 đã XOÁ bản thứ hai của "sửa/xoá mọi trạng thái" (route PATCH và
 * POST trần) vì nó bị route của PaymentRequestController che, chưa bao giờ chạy,
 * và không kiểm tra dữ liệu nhập. Các test dưới đây khoá lại rằng tính năng vẫn
 * chạy đúng qua controller thường, và hai đường đã xoá không sống lại.
 */
final class PrivilegedPaymentRequestCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** Email được gắn cứng trong code làm điều kiện đặc quyền. */
    private const PRIVILEGED_EMAIL = 'buibichthao@egosolar.vn';

    private User $privileged;

    private User $ordinary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privileged = $this->userWithRole('accounting', ['email' => self::PRIVILEGED_EMAIL]);
        $this->ordinary = $this->userWithRole('accounting');
    }

    /**
     * Tạo 1 ĐNTT thẳng trong DB.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function seedPaymentRequest(array $overrides = []): int
    {
        return (int) DB::table('payment_requests')->insertGetId(array_merge([
            'code' => 'PR-'.now()->format('Y').'-90001',
            'receiver_name' => 'Người nhận Test',
            'amount' => 5_000_000,
            'status' => 'accounting_approved',
            'created_by' => $this->ordinary->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * ĐÂY LÀ TÍNH NĂNG THẬT: người có đặc quyền sửa được phiếu ĐÃ kế toán duyệt,
     * qua đúng đường mà form đang gửi (PUT -> PaymentRequestController).
     *
     * Test này là lý do có thể yên tâm xoá bản thứ hai: nó chứng minh khả năng
     * đó không nằm ở bản đã xoá.
     */
    public function test_privileged_user_can_edit_an_approved_request_through_the_normal_form(): void
    {
        $id = $this->seedPaymentRequest([
            'status' => 'accounting_approved',
            'receiver_name' => 'Tên cũ',
        ]);

        $this->actingAs($this->privileged)->put('/payment-requests/'.$id, [
            'receiver_name' => 'Tên đã sửa',
            'amount' => '2000000',
            'reason' => 'Lý do',
            'payment_content' => 'Nội dung',
            'payment_due_date' => now()->toDateString(),
        ]);

        $this->assertSame(
            'Tên đã sửa',
            DB::table('payment_requests')->where('id', $id)->value('receiver_name'),
            'Người có đặc quyền phải sửa được phiếu đã kế toán duyệt.',
        );
    }

    /** Người thường KHÔNG sửa được phiếu đã kế toán duyệt. */
    public function test_ordinary_user_cannot_edit_an_approved_request(): void
    {
        $id = $this->seedPaymentRequest([
            'status' => 'accounting_approved',
            'receiver_name' => 'Tên cũ',
        ]);

        $this->actingAs($this->ordinary)->put('/payment-requests/'.$id, [
            'receiver_name' => 'Tên đã sửa',
            'amount' => '2000000',
            'reason' => 'Lý do',
            'payment_content' => 'Nội dung',
            'payment_due_date' => now()->toDateString(),
        ]);

        $this->assertSame(
            'Tên cũ',
            DB::table('payment_requests')->where('id', $id)->value('receiver_name'),
        );
    }

    /**
     * Hai đường của bản thứ hai đã xoá thì phải TIẾP TỤC không tồn tại.
     *
     * Đăng ký lại chúng sẽ lại che route của PaymentRequestController và âm thầm
     * đổi phân quyền sửa phiếu tài chính — đúng cái bẫy đã mất công gỡ.
     */
    public function test_the_shadowing_routes_stay_deleted(): void
    {
        foreach (['PATCH', 'POST'] as $method) {
            $matched = null;

            try {
                $matched = Route::getRoutes()->match(Request::create('/payment-requests/5', $method));
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                // Không khớp route nào — đúng như mong đợi.
            }

            $this->assertNull(
                $matched,
                $method.' /payment-requests/{id} đã sống lại và đang che route của PaymentRequestController.',
            );
        }
    }

    /** PUT vẫn do controller thường phục vụ. */
    public function test_put_is_served_by_the_ordinary_controller(): void
    {
        $route = Route::getRoutes()->match(Request::create('/payment-requests/5', 'PUT'));

        $this->assertSame('payment_requests.update', $route->getName());
        $this->assertStringContainsString('PaymentRequestController@update', $route->getActionName());
    }

    /**
     * Người thường KHÔNG đi vào nhánh đặc quyền — request rơi về
     * PaymentRequestController::update() như bình thường.
     */
    public function test_ordinary_user_is_delegated_to_the_normal_controller(): void
    {
        $id = $this->seedPaymentRequest(['status' => 'accounting_approved']);

        $response = $this->actingAs($this->ordinary)
            ->put('/payment-requests/'.$id, ['amount' => '9.999.999']);

        // Không khẳng định controller thường cho phép hay chặn — chỉ chốt rằng
        // nó KHÔNG được ghi bằng nhánh đặc quyền.
        $this->assertNotSame(
            9_999_999.0,
            (float) DB::table('payment_requests')->where('id', $id)->value('amount'),
            'Người thường không được đi qua nhánh sửa đặc quyền.',
        );
        $this->assertNotSame(500, $response->getStatusCode());
    }

    /**
     * DELETE thường: do bị controller ghi đè nên đây là hành vi của
     * PaymentRequestController@destroy, KHÔNG phải nhánh đặc quyền.
     * Chốt lại để việc tách code không làm đổi kết quả người dùng nhìn thấy.
     */
    public function test_delete_removes_request_and_unlinks_debt_payments(): void
    {
        $id = $this->seedPaymentRequest();
        $debtId = $this->seedDebtWithPaymentLinkedTo($id);

        $this->actingAs($this->privileged)
            ->delete('/payment-requests/'.$id)
            ->assertRedirect('/payment-requests');

        // Bảng payment_requests KHÔNG có cột deleted_at, nên nhánh `hasColumn`
        // trong code rơi xuống xoá CỨNG. Chốt đúng sự thật đó.
        $this->assertDatabaseMissing('payment_requests', ['id' => $id]);

        $round = DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', $debtId)
            ->first();

        $this->assertNull($round->payment_request_id, 'Đợt thanh toán phải được gỡ liên kết.');
        $this->assertSame('planned', $round->status);
    }

    /** Endpoint `xoa-full-thao` cho ra đúng kết quả như DELETE thường. */
    public function test_thao_full_delete_endpoint_behaves_like_privileged_delete(): void
    {
        $id = $this->seedPaymentRequest();
        $debtId = $this->seedDebtWithPaymentLinkedTo($id);

        $this->actingAs($this->privileged)
            ->delete('/payment-requests/'.$id.'/xoa-full-thao')
            ->assertRedirect('/payment-requests');

        $this->assertDatabaseMissing('payment_requests', ['id' => $id]);
        $this->assertNull(
            DB::table('finance_supplier_debt_payments')->where('supplier_debt_id', $debtId)->value('payment_request_id'),
        );
    }

    /**
     * Người không có đặc quyền bị chặn 403 ở endpoint xoá đặc quyền.
     *
     * Dùng phiếu trạng thái `draft` để middleware LockCompletedFinanceRecords
     * KHÔNG chen vào trước — có vậy mới đo được chính cái chốt 403 của route.
     */
    public function test_ordinary_user_cannot_use_privileged_delete_endpoint(): void
    {
        $id = $this->seedPaymentRequest(['status' => 'draft']);

        $this->actingAs($this->ordinary)
            ->delete('/payment-requests/'.$id.'/xoa-full-thao')
            ->assertForbidden();

        $this->assertDatabaseHas('payment_requests', ['id' => $id]);
    }

    /**
     * Trên phiếu ĐÃ hoàn thành, người thường bị chặn SỚM HƠN — bởi middleware
     * LockCompletedFinanceRecords, trả 302 kèm lỗi chứ không phải 403.
     *
     * Ghi lại để việc tách code không vô tình đổi thứ tự hai lớp chặn này.
     */
    public function test_completed_request_is_blocked_by_middleware_before_route_guard(): void
    {
        $id = $this->seedPaymentRequest(['status' => 'accounting_approved']);

        $this->actingAs($this->ordinary)
            ->from('/payment-requests')
            ->delete('/payment-requests/'.$id.'/xoa-full-thao')
            ->assertRedirect('/payment-requests')
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('payment_requests', ['id' => $id]);
    }

    /**
     * `force-delete-by-thao` xoá phiếu RỒI tính lại paid_amount/status của công nợ.
     *
     * Đây là điểm khác biệt duy nhất so với các endpoint xoá còn lại.
     */
    public function test_force_delete_recalculates_supplier_debt_totals(): void
    {
        $id = $this->seedPaymentRequest(['amount' => 4_000_000, 'status' => 'accounting_approved']);
        $debtId = $this->seedDebtWithPaymentLinkedTo($id, total: 10_000_000, roundAmount: 4_000_000, paid: 4_000_000);

        $this->actingAs($this->privileged)
            ->post('/payment-requests/'.$id.'/force-delete-by-thao')
            ->assertRedirect('/payment-requests');

        $debt = DB::table('finance_supplier_debts')->where('id', $debtId)->first();

        // Đợt thanh toán bị gỡ liên kết và trả về 'planned' -> không còn tính là đã trả.
        $this->assertSame(0.0, (float) $debt->paid_amount);
        $this->assertSame('unpaid', $debt->status);
    }

    /** Sao chép phiếu: mã mới, về nháp, xoá dấu vết duyệt, giữ số tiền. */
    public function test_copy_creates_a_fresh_draft_payment_request(): void
    {
        // `draft` để middleware khoá-phiếu-hoàn-thành không chen vào; các cột
        // duyệt vẫn set sẵn để kiểm tra bản sao có xoá sạch chúng không.
        $id = $this->seedPaymentRequest([
            'code' => 'PR-'.now()->format('Y').'-00042',
            'amount' => 3_300_000,
            'status' => 'draft',
            'accounting_approved_by' => $this->ordinary->id,
            'accounting_approved_at' => now(),
            'accounting_note' => 'Đã duyệt',
        ]);

        $this->actingAs($this->ordinary)
            ->post('/payment-requests/'.$id.'/ban-sao')
            ->assertRedirect();

        $copy = DB::table('payment_requests')
            ->where('id', '>', $id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($copy);
        $this->assertSame('draft', $copy->status, 'Bản sao phải quay về nháp.');
        $this->assertSame(3_300_000.0, (float) $copy->amount);
        $this->assertNull($copy->accounting_approved_by, 'Phải xoá thông tin duyệt của phiếu cũ.');
        $this->assertNull($copy->accounting_approved_at);
        $this->assertSame($this->ordinary->id, (int) $copy->created_by, 'Người tạo là người đang đăng nhập.');
        $this->assertMatchesRegularExpression(
            '/^PR-'.now()->format('Y').'-\d{5}$/',
            (string) $copy->code,
            'Mã phiếu mới phải theo dạng PR-<năm>-<5 số>.',
        );
        $this->assertNotSame('PR-'.now()->format('Y').'-00042', $copy->code);
    }

    /** Sao chép phiếu không tồn tại -> 404. */
    public function test_copy_on_missing_row_returns_404(): void
    {
        $this->actingAs($this->ordinary)
            ->post('/payment-requests/99999999/ban-sao')
            ->assertNotFound();
    }

    /**
     * Tạo công nợ + 1 đợt thanh toán trỏ tới ĐNTT, trả về id công nợ.
     */
    private function seedDebtWithPaymentLinkedTo(
        int $paymentRequestId,
        float $total = 10_000_000,
        float $roundAmount = 5_000_000,
        float $paid = 0,
    ): int {
        $debtId = (int) DB::table('finance_supplier_debts')->insertGetId([
            'supplier_name' => 'NCC ĐNTT',
            'company_name' => 'Công ty Test',
            'document_no' => 'HD-PR-001',
            'document_date' => now()->toDateString(),
            'debt_month' => now()->startOfMonth()->toDateString(),
            'total_amount' => $total,
            'paid_amount' => $paid,
            'status' => $paid > 0 ? 'partial' : 'unpaid',
            'created_by' => $this->ordinary->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('finance_supplier_debt_payments')->insert([
            'supplier_debt_id' => $debtId,
            'payment_request_id' => $paymentRequestId,
            'amount' => $roundAmount,
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $debtId;
    }
}
