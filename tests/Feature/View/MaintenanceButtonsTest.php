<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Canh giữ đợt chuyển nút của trang bảo trì (trang nhiều nút nhất: 27 cái).
 *
 * ## Vì sao cần
 * Khi chuyển `<button class="btn btn-primary">` sang `<x-ui.button>`, có một cái bẫy mà
 * KHÔNG phép so hình ảnh nào bắt được: `<button>` không khai `type` thì trình duyệt mặc
 * định là **submit**, còn component mặc định là **button**. Chuyển thẳng sẽ làm 10 nút
 * ngừng gửi form — trang trông y hệt, chỉ là bấm không ăn.
 *
 * Test này chốt lại: các nút gửi form vẫn phải là submit.
 */
final class MaintenanceButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const URI = '/du-an/bao-tri-bao-hanh';

    /** Trang không còn class nút của Bootstrap (btn-close cũng đã thành <x-ui.close-button>). */
    public function test_khong_con_class_nut_bootstrap(): void
    {
        $source = file_get_contents(resource_path('views/technical/maintenance/index.blade.php'));

        $this->assertStringNotContainsString('class="btn btn-', (string) $source);
    }

    /** Ba tab đều render 200 và có nút dựng từ component. */
    public function test_ba_tab_deu_render_duoc(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        foreach (['maintenance', 'claims', 'stock'] as $view) {
            $res = $this->actingAs($user)->get(self::URI.'?view='.$view);

            $res->assertOk();
            $this->assertStringContainsString('tw:inline-block', (string) $res->getContent(), "Tab {$view} không có nút component");
        }
    }

    /**
     * Nút nằm trong form phải giữ type="submit".
     *
     * Đo trước khi chuyển: 10 nút không khai type và nằm trong <form>, tức mặc định
     * submit. Nếu ai bỏ type đi, chúng thành type=button và lặng lẽ ngừng gửi form.
     */
    public function test_nut_gui_form_van_la_submit(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        $html = (string) $this->actingAs($user)->get(self::URI.'?view=maintenance')->getContent();

        $submits = preg_match_all('/<button[^>]*type="submit"[^>]*>/', $html);

        $this->assertGreaterThanOrEqual(8, $submits, 'Số nút submit tụt xuống — nhiều khả năng có nút vừa mất khả năng gửi form');
    }

    /** CSS riêng của trang không còn quy tắc nào bám vào `.btn` nữa. */
    public function test_css_trang_khong_con_bam_class_btn(): void
    {
        $css = (string) file_get_contents(public_path('css/technical-maintenance-v4.css'));

        $this->assertStringNotContainsString('.btn', $css);
    }
}
