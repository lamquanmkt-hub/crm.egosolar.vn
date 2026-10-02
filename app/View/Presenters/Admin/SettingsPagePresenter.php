<?php

declare(strict_types=1);

namespace App\View\Presenters\Admin;

use App\DTOs\Admin\PermissionMatrixCell;
use App\DTOs\Admin\PermissionMatrixRow;
use App\Enums\PermissionAction;

/**
 * Chuẩn bị trang cài đặt phân quyền (`admin/settings/index`).
 *
 * Một view phục vụ nhiều section (overview/pages/menus/actions/audit) qua các route khác nhau, nên
 * trước đây nó tự suy nhãn, mô tả, route lưu và danh sách quyền đang chọn trong hai khối `@php`,
 * cộng hai khối một dòng lồng trong vòng lặp ma trận để tra ngược hành động theo module.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 */
final class SettingsPagePresenter
{
    /** Route của từng section khi CHƯA chọn vai trò (vai trò đi ở query string). */
    private const SECTION_ROUTES = [
        'overview' => 'admin.settings.index',
        'appearance' => 'admin.settings.appearance',
        'roles' => 'admin.settings.roles',
        'pages' => 'admin.settings.pages',
        'menus' => 'admin.settings.menus',
        'actions' => 'admin.settings.actions',
    ];

    /**
     * Route khi ĐÃ chọn vai trò — dạng chuẩn REST, vai trò nằm trong đường dẫn
     * (`/cai-dat/roles/{role}/quyen-trang`) thay vì `?role=20`.
     */
    private const SECTION_ROUTES_WITH_ROLE = [
        'pages' => 'admin.settings.roles.pages.show',
        'menus' => 'admin.settings.roles.menus.show',
        'actions' => 'admin.settings.roles.actions.show',
        'audit' => 'admin.settings.audit',
    ];

    /** Nhãn + icon của từng mục trên thanh điều hướng cài đặt. */
    private const SECTION_META = [
        'overview' => ['Tổng quan', 'bi-grid-1x2'],
        'appearance' => ['Giao diện & thương hiệu', 'bi-palette'],
        'roles' => ['Vai trò & nhân sự', 'bi-people'],
        'pages' => ['Phân quyền trang', 'bi-window-stack'],
        'menus' => ['Phân quyền menu', 'bi-layout-sidebar-inset'],
        'actions' => ['Quyền thao tác', 'bi-shield-check'],
        'audit' => ['Nhật ký thay đổi', 'bi-clock-history'],
    ];

    /** Nhãn, mô tả và route lưu cho ba section có form phân quyền. */
    private const SECTION_FORM = [
        'pages' => [
            'Phân quyền trang',
            'Bỏ quyền tại đây sẽ chặn truy cập URL trực tiếp bằng middleware backend.',
            'admin.settings.roles.pages',
        ],
        'menus' => [
            'Phân quyền menu',
            'Chỉ quyết định mục nào xuất hiện trên sidebar; không thay thế quyền trang.',
            'admin.settings.roles.menus',
        ],
        'actions' => [
            'Quyền thao tác nghiệp vụ',
            'Kiểm soát tạo, sửa, xóa, duyệt, xuất dữ liệu và các thao tác chuyên môn.',
            'admin.settings.roles.actions',
        ],
    ];

    /**
     * @param  object|null  $selectedRole  vai trò đang xem; null khi chưa chọn
     * @param  list<string>  $selectedPageNames
     * @param  list<string>  $selectedMenuNames
     * @param  list<string>  $selectedBusinessNames
     * @param  iterable<array<string, mixed>>  $actionMatrix  mỗi phần tử có key/label/icon/actions
     * @return array{selectedRoleId: int|null, sectionRoutes: array<string, string>, sectionRoutesWithRole: array<string, string>, sectionMeta: array<string, array{0: string, 1: string}>, permissionSection: string, isPageSection: bool, isMenuSection: bool, sectionTitle: string, sectionDescription: string, saveRoute: string, selectedNames: list<string>, actionColumns: list<array{action: string, label: string, icon: string, destructive: bool}>, matrixRows: list<PermissionMatrixRow>}
     */
    public function viewData(
        string $section,
        ?object $selectedRole,
        array $selectedPageNames,
        array $selectedMenuNames,
        array $selectedBusinessNames,
        iterable $actionMatrix,
    ): array {
        $isPageSection = $section === 'pages';
        $isMenuSection = $section === 'menus';

        // Section nào không có form phân quyền thì dùng bộ nhãn của 'actions', đúng như bản cũ
        // (ternary lồng: pages -> menus -> còn lại).
        [$title, $description, $saveRoute] = self::SECTION_FORM[$section] ?? self::SECTION_FORM['actions'];

        $selectedNames = match (true) {
            $isPageSection => $selectedPageNames,
            $isMenuSection => $selectedMenuNames,
            default => $selectedBusinessNames,
        };

        $actionColumns = array_map(
            static fn (PermissionAction $action): array => [
                'action' => $action->value,
                'label' => $action->label(),
                'icon' => $action->icon(),
                'destructive' => $action->isDestructive(),
            ],
            PermissionAction::ordered()
        );

        // Vai `admin` luôn có mọi quyền: ô bị khoá và view gửi kèm input hidden để không mất quyền.
        $locked = ($selectedRole->name ?? null) === 'admin';

        return [
            'selectedRoleId' => isset($selectedRole->id) ? (int) $selectedRole->id : null,
            'sectionRoutes' => self::SECTION_ROUTES,
            'sectionRoutesWithRole' => self::SECTION_ROUTES_WITH_ROLE,
            'sectionMeta' => self::SECTION_META,
            'permissionSection' => $section,
            'isPageSection' => $isPageSection,
            'isMenuSection' => $isMenuSection,
            'sectionTitle' => $title,
            'sectionDescription' => $description,
            'saveRoute' => $saveRoute,
            'selectedNames' => $selectedNames,
            'actionColumns' => $actionColumns,
            'matrixRows' => $this->matrixRows($actionMatrix, $actionColumns, $selectedNames, $locked),
        ];
    }

    /**
     * Gióng sẵn ô theo thứ tự cột.
     *
     * Bản cũ tra ngược bằng `collect($module['actions'])->keyBy('action')` cho TỪNG dòng rồi
     * `->get()` cho từng cột — tức dựng lại bản đồ ở mỗi dòng.
     *
     * @param  iterable<array<string, mixed>>  $actionMatrix
     * @param  list<array{action: string, label: string, icon: string, destructive: bool}>  $columns
     * @param  list<string>  $selectedNames
     * @return list<PermissionMatrixRow>
     */
    private function matrixRows(iterable $actionMatrix, array $columns, array $selectedNames, bool $locked): array
    {
        $rows = [];

        foreach ($actionMatrix as $module) {
            $byAction = [];
            foreach ((array) ($module['actions'] ?? []) as $item) {
                $byAction[(string) ($item['action'] ?? '')] = $item;
            }

            $cells = [];
            foreach ($columns as $column) {
                $cell = $byAction[$column['action']] ?? null;
                $name = (string) ($cell['name'] ?? '');

                $cells[] = new PermissionMatrixCell(
                    action: $column['action'],
                    actionLabel: $column['label'],
                    destructive: $column['destructive'],
                    available: $cell !== null,
                    permissionName: $name,
                    description: (string) ($cell['description'] ?? ''),
                    checked: $name !== '' && in_array($name, $selectedNames, true),
                    locked: $locked,
                );
            }

            $key = (string) ($module['key'] ?? '');
            $label = (string) ($module['label'] ?? '');

            $rows[] = new PermissionMatrixRow(
                key: $key,
                label: $label,
                icon: (string) ($module['icon'] ?? ''),
                searchText: strtolower($label.' '.$key),
                cells: $cells,
            );
        }

        return $rows;
    }
}
