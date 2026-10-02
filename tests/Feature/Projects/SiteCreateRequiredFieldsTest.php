<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Enums\Role;
use App\Models\Core\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Form tạo công trình phải thu đủ dữ liệu mà bảng điều phối dự án cần.
 *
 * ## Vì sao
 * `sites/create` và `projects-unified/create` cùng ghi vào bảng `sites`, nhưng
 * hai bên trước đây có ràng buộc khác nhau: luồng hợp nhất bắt buộc
 * `lead_engineer_id` và `priority`, form công trình thì không. Hệ quả đo được
 * trên production ngày 2026-09-04: **13/19 công trình thiếu `lead_engineer_id`**
 * — vào bảng điều phối dự án nhưng không có người phụ trách.
 *
 * ⚠️ `address` giữ `max:255` đúng theo kiểu cột `varchar(255)`. Luồng hợp nhất
 * khai `max:700`; chép sang sẽ để MariaDB cắt cụt im lặng.
 *
 * ⚠️ `company_id` cũng phải bắt buộc: cột `sites.company_id` là `NOT NULL`, còn
 * `SiteController@store` ép `null` khi ô để trống, nên bỏ trống ô Công ty trả về
 * **HTTP 500** (SQLSTATE 23000) thay vì báo lỗi nhập liệu. Form đã đánh dấu
 * `required` từ trước, chỉ thiếu luật phía máy chủ.
 */
final class SiteCreateRequiredFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private ?Company $company = null;

    /** @param  array<string, mixed>  $override @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Cong trinh kiem thu',
            'address' => '12 Nguyen Trai, Q1',
            'project_type' => 'residential',
            'priority' => 'normal',
            'company_id' => $this->company()->id,
        ], $override);
    }

    private function actor(): User
    {
        return $this->userWithRole(Role::Admin->value, ['is_active' => 1]);
    }

    private function company(): Company
    {
        return $this->company ??= Company::query()->create([
            'code' => 'KT'.mt_rand(1000, 9999),
            'name' => 'Cong ty kiem thu',
        ]);
    }

    public function test_thieu_ky_su_phu_trach_thi_khong_tao_duoc(): void
    {
        $this->actingAs($this->actor())
            ->post(route('sites.store'), $this->payload())
            ->assertSessionHasErrors('lead_engineer_id');
    }

    public function test_thieu_dia_chi_thi_khong_tao_duoc(): void
    {
        $engineer = $this->actor();

        $this->actingAs($engineer)
            ->post(route('sites.store'), $this->payload([
                'address' => '', 'lead_engineer_id' => $engineer->id,
            ]))
            ->assertSessionHasErrors('address');
    }

    public function test_muc_uu_tien_ngoai_danh_sach_bi_tu_choi(): void
    {
        $engineer = $this->actor();

        $this->actingAs($engineer)
            ->post(route('sites.store'), $this->payload([
                'priority' => 'sieu-khan', 'lead_engineer_id' => $engineer->id,
            ]))
            ->assertSessionHasErrors('priority');
    }

    public function test_ky_su_khong_ton_tai_bi_tu_choi(): void
    {
        $this->actingAs($this->actor())
            ->post(route('sites.store'), $this->payload(['lead_engineer_id' => 99999999]))
            ->assertSessionHasErrors('lead_engineer_id');
    }

    /**
     * Bỏ trống ô Công ty phải ra lỗi nhập liệu, KHÔNG phải HTTP 500.
     *
     * Trước khi siết luật: `SiteController@store` ghi `company_id = null` vào cột
     * `NOT NULL` → QueryException 1048 → trang lỗi 500, người dùng mất hết dữ liệu
     * vừa nhập.
     */
    public function test_thieu_cong_ty_thi_bao_loi_chu_khong_no_500(): void
    {
        $engineer = $this->actor();

        $response = $this->actingAs($engineer)
            ->post(route('sites.store'), $this->payload([
                'company_id' => '', 'lead_engineer_id' => $engineer->id,
            ]));

        $response->assertSessionHasErrors('company_id');
        $this->assertNotSame(500, $response->getStatusCode(), 'không được nổ 500');
    }

    public function test_du_truong_thi_tao_duoc_va_luu_dung(): void
    {
        $engineer = $this->actor();

        $this->actingAs($engineer)
            ->post(route('sites.store'), $this->payload([
                'lead_engineer_id' => $engineer->id, 'priority' => 'high',
            ]))
            ->assertSessionHasNoErrors();

        $site = DB::table('sites')->where('name', 'Cong trinh kiem thu')->latest('id')->first();

        $this->assertNotNull($site, 'phải tạo được công trình');
        $this->assertSame((int) $engineer->id, (int) $site->lead_engineer_id);
        $this->assertSame('high', $site->priority);
        $this->assertSame('12 Nguyen Trai, Q1', $site->address);
        $this->assertSame((int) $this->company()->id, (int) $site->company_id);
    }

    /** Form phải THỰC SỰ có hai ô đó, không chỉ có luật validate. */
    public function test_form_co_du_hai_o_moi(): void
    {
        $html = (string) $this->actingAs($this->actor())->get(route('sites.create'))->getContent();

        $this->assertStringContainsString('name="lead_engineer_id"', $html);
        $this->assertStringContainsString('name="priority"', $html);
    }
}
