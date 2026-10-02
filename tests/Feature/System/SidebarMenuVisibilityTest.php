<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Services\System\SidebarMenuVisibilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cờ hiện/ẩn menu sidebar.
 *
 * Logic này từng nằm trong `partials/sidebar.blade.php` nên không test được;
 * chuyển sang service rồi thì chốt lại đúng những điểm dễ vỡ nhất khi ai đó sửa
 * ma trận workspace.
 */
final class SidebarMenuVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function khach_chua_dang_nhap_khong_thay_menu_nao(): void
    {
        $co = $this->service()->viewData();

        foreach ($co as $ten => $giaTri) {
            if (! str_starts_with($ten, 'egoCan') || $ten === 'egoCanPaymentRequestsMenu') {
                continue;
            }

            $this->assertFalse($giaTri, "Khách chưa đăng nhập mà \$$ten = true.");
        }
    }

    #[Test]
    public function admin_khong_o_workspace_nao_thi_thay_toan_bo_menu(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $co = $this->service()->viewData();

        $this->assertFalse($co['egoWorkspaceMode']);
        $this->assertTrue($co['egoSidebarIsAdmin']);
        $this->assertTrue($co['egoCanSettingsMenu']);
        $this->assertTrue($co['egoCanFinanceMenu']);
        $this->assertTrue($co['egoCanHrMenu']);
    }

    /**
     * Admin vào được mọi workspace, nhưng sidebar phải theo workspace ĐANG CHỌN.
     * Đây là điểm dễ hỏng nhất: bỏ tầng ghi đè đi thì admin ở workspace "hr" lại
     * nhìn thấy đủ menu của cả công ty.
     */
    #[Test]
    public function admin_dang_o_workspace_phong_ban_thi_menu_bi_thu_theo_ma_tran(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        session(['active_workspace' => 'hr']);

        $co = $this->service()->viewData();

        $this->assertTrue($co['egoWorkspaceMode']);
        $this->assertTrue($co['egoCanHrMenu'], 'Workspace hr phải thấy menu nhân sự.');
        $this->assertFalse($co['egoCanFinanceMenu'], 'Workspace hr không được thấy menu tài chính.');
        $this->assertFalse($co['egoCanSettingsMenu'], 'Cài đặt không thuộc workspace phòng ban.');

        // Vai trò thật KHÔNG bị workspace làm thay đổi.
        $this->assertTrue($co['egoSidebarIsAdmin']);
        $this->assertTrue($co['egoSidebarIsExecutive']);
    }

    #[Test]
    public function workspace_dieu_hanh_khong_bat_che_do_ghi_de(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        session(['active_workspace' => 'executive']);

        $co = $this->service()->viewData();

        $this->assertFalse($co['egoWorkspaceMode'], 'executive phải giữ menu đầy đủ theo vai trò.');
        $this->assertTrue($co['egoCanSettingsMenu']);
    }

    #[Test]
    public function workspace_khong_co_trong_ma_tran_thi_bo_qua(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        session(['active_workspace' => 'khong_ton_tai']);

        $co = $this->service()->viewData();

        $this->assertFalse($co['egoWorkspaceMode']);
        $this->assertSame([], $co['egoWorkspaceAllowedMenus']);
        $this->assertTrue($co['egoCanFinanceMenu']);
    }

    /** Đề nghị thanh toán mở cho mọi workspace — cố ý, không phải sót. */
    #[Test]
    #[DataProvider('cacWorkspace')]
    public function de_nghi_thanh_toan_luon_mo(string $workspace): void
    {
        $this->actingAs($this->userWithRole('sales'));
        session(['active_workspace' => $workspace]);

        $this->assertTrue($this->service()->viewData()['egoCanPaymentRequestsMenu']);
    }

    /** @return array<string, array{string}> */
    public static function cacWorkspace(): array
    {
        $ra = [];

        foreach (array_keys(SidebarMenuVisibilityService::WORKSPACE_MENU_MATRIX) as $ws) {
            $ra[$ws] = [$ws];
        }

        return $ra;
    }

    #[Test]
    public function moi_workspace_deu_co_menu_bang_dieu_khien(): void
    {
        foreach (SidebarMenuVisibilityService::WORKSPACE_MENU_MATRIX as $ws => $menus) {
            $this->assertContains(
                'menu.dashboard',
                $menus,
                "Workspace $ws không có menu.dashboard — người dùng vào sẽ không có đường về trang chủ."
            );
        }
    }

    private function service(): SidebarMenuVisibilityService
    {
        return app(SidebarMenuVisibilityService::class);
    }
}
