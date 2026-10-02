<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chặn `@error(...)` (và directive khác) nằm TRONG thuộc tính của thẻ component `<x-...>`.
 *
 * ## Vì sao — lỗi này đang chạy trên production
 * Blade **không biên dịch** directive nằm trong thuộc tính của thẻ component: trình biên dịch thẻ
 * component đọc giá trị thuộc tính như chuỗi thô. Kết quả đo trên HTML thật của
 * `hr/employees/edit` (2026-10-01): thuộc tính `class` chứa nguyên văn
 *
 *     class="… tw:rounded-[…] @error('name') is-invalid @enderror"
 *
 * Hệ quả KHÔNG hiển nhiên, đã đo bằng trình duyệt thật:
 *  - `class` tách theo khoảng trắng nên token `is-invalid` VẪN tồn tại → luật anh em
 *    `.is-invalid ~ .invalid-feedback{display:block}` vẫn chạy, thông báo lỗi VẪN HIỆN
 *    (3/3 phần tử `display:block`). Nên nhìn qua tưởng không sao.
 *  - Nhưng viền đỏ thì KHÔNG bao giờ xuất hiện: Bootstrap tô viền bằng `.form-control.is-invalid`,
 *    cần CẢ HAI lớp, mà ô nhập nay là `<x-ui.input>` (Tailwind) nên không còn `.form-control`.
 *    Đo được viền `rgb(222,226,230)` thay vì `rgb(220,53,69)`.
 *  - Và hai token rác `@error('name')` / `@enderror` nằm lại trong `class`.
 *
 * Cách đúng: `:invalid="$errors->has('name')"` (prop của `<x-ui.input|select>`) hoặc
 * `@class([...])` / `{{ }}` — đừng đặt directive vào thuộc tính của thẻ component.
 *
 * ## Danh sách còn nợ CHỈ ĐƯỢC NGẮN ĐI
 * 14 view dưới đây còn dính (đo 2026-10-01). Dọn view nào thì xoá tên khỏi đây.
 */
final class BladeDirectiveTrongThuocTinhTest extends TestCase
{
    /** View chưa dọn — xoá dần, KHÔNG thêm mới. */
    private const CON_TON_DONG = [
        'marketing/reports/weekly_tasks_create',
        'marketing/reports/weekly_tasks_edit',
        'orders/partials/product-table',
        'orders/partials/order-info',
        'users/profile-edit',
        'product-categories/edit',
        'product-categories/create',
        'orders/approval-form',
        'payment_methods/_form',
        'hr/departments/edit',
        'hr/departments/create',
        'hr/positions/edit',
        'hr/positions/create',
    ];

    #[Test]
    public function khong_view_nao_dat_directive_trong_thuoc_tinh_component(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            $ma = (string) preg_replace(
                '/\{\{--.*?--\}\}/s', '', (string) file_get_contents($duongDan)
            );
            $ten = str_replace([resource_path('views').'/', '.blade.php'], '', $duongDan);

            preg_match_all('/<x-[\w.\-]+[^>]*?>/s', $ma, $the);

            foreach ($the[0] as $mot) {
                if (preg_match('/@(error|if|unless|isset|empty|auth|can)\s*\(/', $mot) === 1) {
                    $viPham[$ten] = true;
                }
            }
        }

        $con = array_values(array_diff(array_keys($viPham), self::CON_TON_DONG));
        sort($con);

        $this->assertSame([], $con, implode("\n", [
            'View đặt directive Blade trong thuộc tính của thẻ <x-...>: Blade KHÔNG biên dịch chỗ đó,',
            'chuỗi lọt nguyên văn ra `class` và trạng thái lỗi của ô nhập không bao giờ bật.',
            'Dùng `:invalid="$errors->has(\'ten\')"` hoặc `@class([...])`.',
        ]));

        // Danh sách nợ chỉ được NGẮN đi: tên đã dọn mà còn trong danh sách là phải xoá.
        $daDon = array_values(array_diff(self::CON_TON_DONG, array_keys($viPham)));
        sort($daDon);

        $this->assertSame([], $daDon,
            'Các view này đã hết vi phạm — xoá khỏi CON_TON_DONG: '.implode(', ', $daDon));
    }
}
