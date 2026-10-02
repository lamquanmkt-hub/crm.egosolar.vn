<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Services\Sales\SalesCommissionScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nhận diện nhân sự kinh doanh: phòng ban HOẶC chức danh HOẶC vai trò.
 *
 * ## Vì sao đổi
 * Đo trên production 2026-09-05: trong 235 đơn đã thu đủ tiền, cách cũ (chỉ xét
 * vai trò Spatie `sales`/`sales_manager`) khớp **0 đơn**, nên toàn bộ module hoa
 * hồng — màn hình, Excel, PDF — trả về rỗng. Công ty theo dõi nhân sự kinh doanh
 * bằng phòng ban `Marketing & Sales` (khớp 161 đơn) và chức danh chứa "Sales"
 * (khớp 144 đơn). Chỉ đúng 1 user mang vai trò `sales`, người đó đã nghỉ và tạo
 * 0 đơn.
 */
final class CommissionSalesStaffTest extends TestCase
{
    use DatabaseTransactions;

    public function test_nhan_ra_nguoi_theo_phong_ban(): void
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => SalesCommissionScope::SALES_DEPARTMENT_NAMES[0],
        ]);

        $id = $this->makeUser(['department_id' => $deptId]);

        $this->assertContains($id, $this->salesStaffIds(),
            'user thuộc phòng kinh doanh phải được nhận là nhân sự kinh doanh');
    }

    public function test_nhan_ra_nguoi_theo_chuc_danh(): void
    {
        $posId = DB::table('positions')->insertGetId(['name' => 'Thử Việc Sales']);

        $id = $this->makeUser(['position_id' => $posId]);

        $this->assertContains($id, $this->salesStaffIds(),
            'user có chức danh chứa "Sales" phải được nhận là nhân sự kinh doanh');
    }

    public function test_van_nhan_ra_nguoi_theo_vai_tro_spatie(): void
    {
        $user = $this->userWithRole('sales', ['is_active' => 1]);

        $this->assertContains((int) $user->id, $this->salesStaffIds(),
            'giữ vai trò Spatie làm tín hiệu bổ sung — gán đúng vai trò vẫn phải nhận ra');
    }

    public function test_khong_nhan_nguoi_khong_dinh_dang_nao(): void
    {
        $deptId = DB::table('departments')->insertGetId(['name' => 'Kỹ Thuật']);
        $posId = DB::table('positions')->insertGetId(['name' => 'Kỹ sư']);

        $id = $this->makeUser(['department_id' => $deptId, 'position_id' => $posId]);

        $this->assertNotContains($id, $this->salesStaffIds(),
            'người ngoài bộ phận kinh doanh không được lọt vào diện tính hoa hồng');
    }

    /**
     * Người đã nghỉ VẪN phải nằm trong danh sách.
     *
     * Báo cáo hoa hồng là báo cáo theo kỳ; lọc `is_active` thì mở lại tháng cũ sẽ
     * mất người và mất tiền. Trên production 11/12 nhân sự phòng kinh doanh đang
     * `is_active = 0` và họ tạo 149/161 đơn đã thu đủ tiền.
     */
    public function test_nguoi_da_nghi_van_duoc_tinh(): void
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => SalesCommissionScope::SALES_DEPARTMENT_NAMES[0],
        ]);

        $id = $this->makeUser(['department_id' => $deptId, 'is_active' => 0]);

        $this->assertContains($id, $this->salesStaffIds(),
            'người đã nghỉ vẫn phải xuất hiện ở kỳ họ còn làm');
    }

    /** @param array<string, mixed> $attributes */
    private function makeUser(array $attributes): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'name' => 'Kiem thu '.uniqid(),
            'email' => uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'is_active' => 1,
        ], $attributes));
    }

    /** @return list<int> */
    private function salesStaffIds(): array
    {
        $query = DB::table('users as u')->select('u.id');

        SalesCommissionScope::constrainToSalesStaff($query, 'u');

        return $query->pluck('u.id')->map(static fn ($id): int => (int) $id)->all();
    }
}
