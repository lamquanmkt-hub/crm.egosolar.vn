<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Ai vào được module tài chính — khoá lại bằng test thay vì bằng trí nhớ.
 *
 * ## Vì sao cần
 * Quyền vào trang tài chính hiện do `EnforcePageAccess` + `PageAccessService`
 * quyết định, và luật đó có một nhánh dễ hiểu nhầm: `canAccess()` sẽ CHO QUA khi
 * `pageControlEnabled()` trả false — tức khi role của người dùng chưa được gán
 * quyền `page.*` nào. Trên production hôm nay cả 13 role đều đã có ít nhất một
 * quyền trang nên nhánh đó không bật, nhưng một role tạo mới mà quên gán quyền
 * sẽ lọt vào /finance.
 *
 * Vì vậy `page.finance` được đưa vào `role_permissions.always_enforce_permissions`:
 * quyền này LUÔN được kiểm, bất kể nhánh trên. Test dưới đây chốt đúng hành vi đó —
 * nếu ai gỡ nó khỏi always_enforce, test đỏ ngay.
 *
 * ## Đo trên production 2026-09-02
 * `page.finance` chỉ được cấp cho admin, accounting, management — nên siết nó
 * không lấy mất quyền của ai đang dùng.
 *
 * `page.payment_requests` thì CỐ Ý không siết: cả 13 role đều đã có quyền này nên
 * nó không lọc ai; lớp chặn thật của module ĐNTT nằm trong controller
 * (`canEditPaymentRequest`, lọc `created_by` trong `applyCommonFilters`).
 */
final class FinanceAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    /** Trang công nợ nhà cung cấp: role không có page.finance thì KHÔNG vào được. */
    public function test_role_khong_co_quyen_tai_chinh_bi_chan(): void
    {
        // Role có quyền trang khác (giống production: mọi role đều có ít nhất một
        // page.*) nhưng KHÔNG có page.finance.
        $user = $this->userWithRole(Role::Marketing->value, permissions: ['page.marketing']);

        $this->actingAs($user)->get('/finance/supplier-debts')->assertForbidden();
        $this->actingAs($user)->get('/payment-methods')->assertForbidden();
    }

    /**
     * Role CHƯA được gán quyền trang nào cũng phải bị chặn.
     *
     * Đây là nhánh mà `pageControlEnabled()` trả false. Nếu `page.finance` bị gỡ
     * khỏi always_enforce_permissions thì người dùng này sẽ VÀO ĐƯỢC — test này
     * tồn tại để chặn đúng chuyện đó.
     */
    public function test_role_chua_co_quyen_trang_nao_van_bi_chan(): void
    {
        $user = $this->userWithRole('role_moi_chua_gan_quyen');

        $this->actingAs($user)->get('/finance/supplier-debts')->assertForbidden();
    }

    /** Kế toán có page.finance thì vào được. */
    public function test_ke_toan_vao_duoc_trang_tai_chinh(): void
    {
        $user = $this->userWithRole(Role::Accounting->value, permissions: ['page.finance']);

        $this->actingAs($user)->get('/finance/supplier-debts')->assertSuccessful();
    }

    /** Admin luôn vào được, không phụ thuộc quyền trang. */
    public function test_admin_luon_vao_duoc(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        $this->actingAs($user)->get('/finance/supplier-debts')->assertSuccessful();
        $this->actingAs($user)->get('/payment-methods')->assertSuccessful();
    }

    /** Người dùng không có role nào thì không vào được gì. */
    public function test_user_khong_co_role_bi_chan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/finance/supplier-debts')->assertForbidden();
        $this->actingAs($user)->get('/payment-methods')->assertForbidden();
    }
}
