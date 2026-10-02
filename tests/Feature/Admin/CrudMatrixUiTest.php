<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ma trận quyền CRUD trên màn "Quyền thao tác" phải có ĐỦ ba phần: markup, CSS
 * và JS.
 *
 * ## Vì sao cần test này
 * Tôi thêm ma trận CRUD với các class `ego-crud-matrix*` và nút "tất cả" dùng
 * `data-crud-row` / `data-crud-column`, nhưng KHÔNG viết CSS và KHÔNG viết JS
 * cho chúng. Kết quả: bảng hiện ra trần trụi không định dạng, nút bấm không có
 * gì xảy ra. Test đơn vị không bắt được vì HTML vẫn render đúng — thứ thiếu nằm
 * ở hai file tài nguyên khác.
 *
 * Đây là lớp kiểm rẻ tiền cho một lỗi tốn kém: class dùng trong Blade mà không
 * có luật CSS, hoặc data-attribute không có mã xử lý.
 */
final class CrudMatrixUiTest extends TestCase
{
    use DatabaseTransactions;

    private const CSS_FILE = 'css/ego-settings-permissions.css';

    private const JS_FILE = 'js/ego-settings-permissions.js';

    /** Mọi class `ego-crud-*` xuất hiện trong HTML đều phải có luật CSS. */
    public function test_every_crud_matrix_class_has_a_style_rule(): void
    {
        $html = $this->renderActionsScreenFor($this->aRoleWithSomePermissions());
        $css = (string) file_get_contents(public_path(self::CSS_FILE));

        $used = [];

        preg_match_all('/class="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $attribute) {
            foreach (preg_split('/\s+/', $attribute) ?: [] as $class) {
                if (str_starts_with($class, 'ego-crud')) {
                    $used[$class] = true;
                }
            }
        }

        $this->assertNotEmpty($used, 'Không tìm thấy ma trận CRUD trong HTML — test này đang không kiểm gì.');

        $missing = [];

        foreach (array_keys($used) as $class) {
            if (preg_match('/\.'.preg_quote($class, '/').'[\s,{:.\[]/', $css) !== 1) {
                $missing[] = $class;
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Class dùng trong Blade nhưng KHÔNG có luật CSS — bảng sẽ hiện ra trần trụi:\n  .".
            implode("\n  .", $missing),
        );
    }

    /** Nút bật/tắt cả hàng và cả cột phải có mã xử lý, không phải nút chết. */
    public function test_row_and_column_toggles_have_javascript(): void
    {
        $html = $this->renderActionsScreenFor($this->aRoleWithSomePermissions());
        $js = (string) file_get_contents(public_path(self::JS_FILE));

        $this->assertStringContainsString('data-crud-row', $html, 'Thiếu nút bật/tắt cả hàng trong HTML.');
        $this->assertStringContainsString('data-crud-column', $html, 'Thiếu nút bật/tắt cả cột trong HTML.');

        foreach (['data-crud-row', 'data-crud-column', 'data-crud-cell'] as $hook) {
            $this->assertStringContainsString(
                $hook,
                $js,
                "HTML có `{$hook}` nhưng JS không xử lý — bấm vào sẽ không có gì xảy ra.",
            );
        }
    }

    /** Ô tick phải phản ánh đúng quyền vai trò đang có. */
    public function test_matrix_ticks_reflect_the_role_permissions(): void
    {
        $role = $this->aRoleWithSomePermissions();
        $html = $this->renderActionsScreenFor($role);

        preg_match_all('/<input[^>]*data-crud-cell[^>]*>/', $html, $inputs);

        $this->assertNotEmpty($inputs[0], 'Không render được ô tick nào.');

        $ticked = array_values(array_filter(
            $inputs[0],
            static fn (string $tag): bool => str_contains($tag, 'checked'),
        ));

        $this->assertCount(
            $role->permissions->count(),
            $ticked,
            'Số ô tick sẵn phải bằng số quyền thao tác vai trò đang có.',
        );
    }

    /**
     * Vai trò admin: khoá toàn bộ ô VÀ phải nói rõ vì sao.
     *
     * Không có lời giải thích thì người dùng bấm mãi không thấy đổi và tưởng
     * chức năng hỏng — đúng phản hồi đã nhận được.
     */
    public function test_admin_role_locks_every_cell_and_explains_why(): void
    {
        $html = $this->renderActionsScreenFor(Role::findOrCreate('admin', 'web'));

        preg_match_all('/<input[^>]*data-crud-cell[^>]*>/', $html, $inputs);

        $this->assertNotEmpty($inputs[0]);

        foreach ($inputs[0] as $tag) {
            $this->assertStringContainsString('disabled', $tag, 'Vai trò admin phải khoá mọi ô tick.');
        }

        $this->assertStringContainsString(
            'ego-settings-explain--locked',
            $html,
            'Phải giải thích vì sao vai trò admin không sửa được, nếu không trông như hỏng.',
        );
    }

    /** Vai trò thường KHÔNG bị khoá và không hiện thông báo khoá. */
    public function test_ordinary_role_is_editable(): void
    {
        $html = $this->renderActionsScreenFor($this->aRoleWithSomePermissions());

        $this->assertStringNotContainsString('ego-settings-explain--locked', $html);

        preg_match_all('/<input[^>]*data-crud-cell[^>]*>/', $html, $inputs);

        foreach ($inputs[0] as $tag) {
            $this->assertStringNotContainsString('disabled', $tag, 'Vai trò thường phải sửa được.');
        }
    }

    private function aRoleWithSomePermissions(): Role
    {
        $role = Role::findOrCreate('vai_tro_ma_tran', 'web');
        $role->syncPermissions([]);

        foreach (['orders.view', 'orders.create', 'customers.update'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        return $role->fresh(['permissions']);
    }

    private function renderActionsScreenFor(Role $role): string
    {
        $admin = $this->userWithRole('admin');

        return $this->actingAs($admin)
            ->get('/cai-dat/roles/'.$role->id.'/quyen-thao-tac')
            ->assertOk()
            ->getContent();
    }
}
