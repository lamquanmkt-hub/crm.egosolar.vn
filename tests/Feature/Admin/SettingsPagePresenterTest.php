<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\DTOs\Admin\PermissionMatrixCell;
use App\DTOs\Admin\PermissionMatrixRow;
use App\View\Presenters\Admin\SettingsPagePresenter;
use Tests\TestCase;

/**
 * SettingsPagePresenter thay 4 khối `@php` của trang cài đặt phân quyền (2026-09-25).
 *
 * Một view phục vụ nhiều section qua các route khác nhau, nên khối 1–2 tự suy nhãn/route/danh sách
 * quyền đang chọn; khối 3–4 là hai dòng lồng trong vòng ma trận, dựng lại bản đồ tra ngược ở MỖI
 * dòng module.
 */
final class SettingsPagePresenterTest extends TestCase
{
    private const VIEW = 'resources/views/admin/settings/index.blade.php';

    /** Khoá view() do controller cấp ngoài presenter. */
    private const CONTROLLER_KEYS = [
        'section', 'roles', 'selectedRole', 'permissions', 'actionMatrix', 'managedActionNames',
        'pageDefinitions', 'menuDefinitions', 'pagePermissions', 'menuPermissions',
        'businessPermissions', 'businessGroups', 'selectedPermissionNames', 'selectedPageNames',
        'selectedMenuNames', 'selectedBusinessNames', 'users', 'audits', 'stats',
    ];

    private const LOOP_AND_BLADE_VARIABLES = [
        'module', 'cell', 'column', 'role', 'user', 'audit', 'item', 'group', 'definition',
        'key', 'label', 'icon', 'permissionName', 'error', 'loop', 'errors', 'slot', 'attributes', 'component',
    ];

    /** @return array<string, mixed> */
    private function matrix(): array
    {
        return [
            [
                'key' => 'orders', 'label' => 'Đơn hàng', 'icon' => 'bi-cart',
                'actions' => [
                    ['action' => 'view', 'name' => 'orders.view', 'description' => 'Xem đơn'],
                    ['action' => 'delete', 'name' => 'orders.delete', 'description' => 'Xoá đơn'],
                ],
            ],
            // Module này KHÔNG có hành động nào -> mọi ô đều `available = false`
            ['key' => 'reports', 'label' => 'Báo cáo', 'icon' => 'bi-graph', 'actions' => []],
        ];
    }

    public function test_view_khong_con_php_va_moi_bien_do_presenter_hoac_controller_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);

        $data = (new SettingsPagePresenter)->viewData('overview', null, [], [], [], []);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)));
    }

    public function test_view_chi_doc_thuoc_tinh_that_cua_hai_dto(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        foreach (['module' => PermissionMatrixRow::class, 'cell' => PermissionMatrixCell::class] as $var => $class) {
            preg_match_all('/\$'.$var.'->([a-zA-Z]+)/', $source, $m);
            $properties = array_map(
                fn (\ReflectionProperty $p) => $p->getName(),
                (new \ReflectionClass($class))->getProperties()
            );
            $this->assertNotSame([], $m[1], $var);
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), $var);
        }
    }

    public function test_nhan_va_route_luu_theo_tung_section(): void
    {
        $svc = new SettingsPagePresenter;

        $pages = $svc->viewData('pages', null, ['page.a'], ['menu.a'], ['orders.view'], []);
        $this->assertTrue($pages['isPageSection']);
        $this->assertFalse($pages['isMenuSection']);
        $this->assertSame('Phân quyền trang', $pages['sectionTitle']);
        $this->assertSame('admin.settings.roles.pages', $pages['saveRoute']);
        $this->assertSame(['page.a'], $pages['selectedNames']);

        $menus = $svc->viewData('menus', null, ['page.a'], ['menu.a'], ['orders.view'], []);
        $this->assertTrue($menus['isMenuSection']);
        $this->assertSame('Phân quyền menu', $menus['sectionTitle']);
        $this->assertSame('admin.settings.roles.menus', $menus['saveRoute']);
        $this->assertSame(['menu.a'], $menus['selectedNames']);

        $actions = $svc->viewData('actions', null, ['page.a'], ['menu.a'], ['orders.view'], []);
        $this->assertSame('Quyền thao tác nghiệp vụ', $actions['sectionTitle']);
        $this->assertSame(['orders.view'], $actions['selectedNames']);
    }

    public function test_section_khong_co_form_thi_dung_bo_nhan_cua_actions(): void
    {
        // Bản cũ là ternary lồng: không phải pages, không phải menus -> rơi về "Quyền thao tác".
        $svc = new SettingsPagePresenter;

        foreach (['overview', 'audit', 'appearance', 'khong_biet'] as $section) {
            $data = $svc->viewData($section, null, [], [], [], []);
            $this->assertSame('Quyền thao tác nghiệp vụ', $data['sectionTitle'], $section);
            $this->assertSame('admin.settings.roles.actions', $data['saveRoute'], $section);
            $this->assertFalse($data['isPageSection'], $section);
            $this->assertFalse($data['isMenuSection'], $section);
        }
    }

    public function test_chua_chon_vai_tro_thi_selected_role_id_la_null(): void
    {
        $svc = new SettingsPagePresenter;

        $this->assertNull($svc->viewData('pages', null, [], [], [], [])['selectedRoleId']);
        $this->assertSame(7, $svc->viewData('pages', (object) ['id' => 7, 'name' => 'sales'], [], [], [], [])['selectedRoleId']);
    }

    public function test_o_ma_tran_duoc_giong_hang_theo_cot_va_bao_thieu_hanh_dong(): void
    {
        $data = (new SettingsPagePresenter)->viewData(
            'actions',
            (object) ['id' => 7, 'name' => 'sales'],
            [], [], ['orders.view'],
            $this->matrix(),
        );

        $columns = array_column($data['actionColumns'], 'action');
        $this->assertSame(['view', 'create', 'update', 'delete'], $columns);

        [$orders, $reports] = $data['matrixRows'];

        $this->assertSame('orders', $orders->key);
        // `strtolower` của PHP làm việc theo BYTE: 'Đ' nhiều byte nên KHÔNG hạ được,
        // kết quả là 'Đơn hàng orders' chứ không phải 'đơn hàng orders'. Bản cũ cũng vậy nên
        // presenter giữ nguyên. Hệ quả có thật: ô tìm kiếm phía client gõ 'đơn' (đ thường) sẽ
        // KHÔNG khớp module này — lỗi có sẵn, sửa nó là đổi hành vi nên để chủ dự án quyết.
        $this->assertSame('Đơn hàng orders', $orders->searchText);
        // Ô phải gióng ĐÚNG thứ tự cột, kể cả cột module không có hành động.
        $this->assertCount(4, $orders->cells);
        $this->assertSame($columns, array_map(fn (PermissionMatrixCell $c) => $c->action, $orders->cells));

        $this->assertTrue($orders->cells[0]->available);
        $this->assertSame('orders.view', $orders->cells[0]->permissionName);
        $this->assertSame('Xem đơn', $orders->cells[0]->description);
        $this->assertTrue($orders->cells[0]->checked, 'orders.view có trong selectedNames');

        $this->assertFalse($orders->cells[1]->available, 'module không có hành động create');
        $this->assertSame('', $orders->cells[1]->permissionName);
        $this->assertFalse($orders->cells[1]->checked);

        $this->assertTrue($orders->cells[3]->available);
        $this->assertFalse($orders->cells[3]->checked, 'orders.delete không có trong selectedNames');

        // Module rỗng: đủ 4 ô nhưng không ô nào dùng được
        $this->assertCount(4, $reports->cells);
        $this->assertSame([false, false, false, false], array_map(fn (PermissionMatrixCell $c) => $c->available, $reports->cells));
    }

    public function test_vai_admin_thi_moi_o_bi_khoa(): void
    {
        $svc = new SettingsPagePresenter;

        $sales = $svc->viewData('actions', (object) ['id' => 7, 'name' => 'sales'], [], [], [], $this->matrix());
        $this->assertFalse($sales['matrixRows'][0]->cells[0]->locked);

        $admin = $svc->viewData('actions', (object) ['id' => 1, 'name' => 'admin'], [], [], [], $this->matrix());
        $this->assertTrue($admin['matrixRows'][0]->cells[0]->locked);
        // Khoá áp cho MỌI ô, kể cả ô module không có hành động
        $this->assertTrue($admin['matrixRows'][1]->cells[2]->locked);
    }

    public function test_cot_pha_huy_duoc_danh_dau(): void
    {
        $data = (new SettingsPagePresenter)->viewData('actions', null, [], [], [], $this->matrix());

        $cells = $data['matrixRows'][0]->cells;
        $this->assertFalse($cells[0]->destructive, 'view không phá huỷ');
        $this->assertTrue($cells[3]->destructive, 'delete là hành động phá huỷ');
    }
}
