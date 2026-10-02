<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Canh các view đã chuyển `.form-control` / `.form-select` / `.form-label` sang
 * lớp component `<x-ui.*>`.
 *
 * ## Vì sao canh ở mức mã nguồn
 * Phép kiểm thật là đo `getComputedStyle` từng phần tử trước/sau bằng Chrome
 * headless (đã chạy khi chuyển: 101/101 phần tử của trang bảo trì giống hệt trên
 * 20 thuộc tính). Test này không lặp lại phép đo đó — nó chỉ giữ cho những gì đã
 * đo không âm thầm quay lại class Bootstrap.
 *
 * ## Ngoại lệ CỐ Ý: ô chọn do Tom Select quản lý
 * Theme `tom-select.bootstrap5` chép class của thẻ gốc lên `.ts-wrapper` rồi tô
 * theo `.form-select`. Bỏ class đi thì `.ts-wrapper:not(.form-control,.form-select)`
 * xoá viền/nền của wrapper, và `.form-select .ts-control input` không còn đặt màu
 * chữ — đo được #212529 -> #343a40. Nên các ô đó GIỮ NGUYÊN Bootstrap và được
 * khai tường minh dưới đây; chuyển chúng phải làm cùng lúc với theme Tom Select.
 */
final class FormControlsConvertedTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2?: int}>
     *                                                          [đường dẫn view, số ô chọn Tom Select được miễn, số `.form-control` được miễn]
     */
    public static function views(): array
    {
        return [
            'bảo trì O&M' => ['technical/maintenance/index.blade.php', 3],
            // 2 ô còn `.form-control` nằm TRONG <script>: chuỗi JS dựng hàng
            // "chi phí khác" lúc chạy, không phải Blade. Luật CSS của trang đã giữ
            // cả `.form-control` lẫn `.site-input` nên hai loại hiển thị như nhau.
            'sửa công trình' => ['sites/edit.blade.php', 0],
            'lịch nội dung' => ['marketing/reports/content_calendar.blade.php', 0],
            'ngân sách marketing' => ['marketing/budget.blade.php', 0],
            'thiết lập KPI marketing' => ['marketing/kpi_payroll/settings.blade.php', 0],
            'sửa chỉ số marketing' => ['marketing/metrics_edit.blade.php', 0],
            'sửa đề xuất' => ['proposals/edit.blade.php', 0],
            'tạo đề xuất' => ['proposals/create.blade.php', 0],
            // 2 ô còn `.form-control` nằm TRONG <script>: hàng thiết bị và hàng vật
            // tư do JS dựng lúc chạy. Luật trang CỐ Ý giữ cả `.form-control` lẫn
            // `.sc-input` — đổi hết sang `.sc-input` là hai hàng đó mất bo góc 13px
            // và màu viền (đã vấp khi chuyển, xem khối <style> của view).
            'tạo công trình' => ['sites/create.blade.php', 0],
            // 8 ô còn class Bootstrap nằm TRONG <script>: mẫu hàng ngày nghỉ do JS
            // nhân bản lúc chạy.
            'cài đặt chấm công' => ['hr/attendance/settings.blade.php', 0],
            'chi tiết nội dung' => ['marketing/reports/content_calendar_show.blade.php', 0],
            'bảng lương' => ['finance/salary.blade.php', 0],
            'sửa công việc tuần' => ['marketing/reports/weekly_tasks_edit.blade.php', 0],
            'tạo nhân viên' => ['hr/employees/create.blade.php', 0],
            'sửa nhân viên' => ['hr/employees/edit.blade.php', 0],
            'tạo công việc tuần' => ['marketing/reports/weekly_tasks_create.blade.php', 0],
            'hồ sơ công ty' => ['company_management/form.blade.php', 0],
            'tạo phiếu chi' => ['finance/payments/create.blade.php', 0],
            'sửa công ty' => ['companies/edit.blade.php', 0],
            'danh sách công trình' => ['sites/index.blade.php', 0],
            'tạo phiếu thu' => ['finance/receipts/create.blade.php', 0],
            'ngân sách tài chính' => ['finance/budget.blade.php', 0],
            'sửa yêu cầu vật tư' => ['material_requests/edit.blade.php', 0],
            // Trang này tạo kiểu bằng tệp CSS NGOÀI
            // (public/css/ego-payment-requests-enterprise.css) chứ không phải <style>
            // trong view; bộ chọn trong tệp đó đã đổi sang .pr-input/.pr-label.
            'đề nghị thanh toán' => ['payment_requests/index.blade.php', 0],
            'quản lý serial' => ['products/serials.blade.php', 0],
            'tạo yêu cầu vật tư' => ['material_requests/create.blade.php', 0],
            'sửa ngân sách marketing' => ['marketing/budget_edit.blade.php', 0],
            'danh sách phiếu thu' => ['finance/receipts/index.blade.php', 0],
            'danh sách phiếu chi' => ['finance/payments/index.blade.php', 0],
            'báo cáo quảng cáo' => ['marketing/report/ads.blade.php', 0],
            'tạo tăng ca' => ['hr/overtime/create.blade.php', 0],
            'tạo làm việc online' => ['hr/online-work/create.blade.php', 0],
            'chi tiết công trình' => ['sites/show.blade.php', 0],
            'chi tiết công việc' => ['tasks/show.blade.php', 0],
            'danh sách nhân viên' => ['hr/employees/index.blade.php', 0],
            'tạo tài khoản quỹ' => ['finance/accounts/create.blade.php', 0],
            'danh mục sản phẩm' => ['products/index.blade.php', 0],
            'sửa công việc' => ['tasks/edit.blade.php', 0],
            'tạo công việc' => ['tasks/create.blade.php', 0],
            'chi tiết đề xuất' => ['proposals/show.blade.php', 0],
            'công việc tuần' => ['marketing/reports/weekly_tasks.blade.php', 0],
            'danh sách tăng ca' => ['hr/overtime/index.blade.php', 0],
            'thống kê chấm công' => ['hr/attendance/index.blade.php', 0],
            // Cụm kho: kiểu lấy từ public/css/ego-inventory-enterprise.css. Bộ chọn
            // trong tệp đó nay dùng `.ego-input`/`.ego-select` — VỐN ĐÃ CÓ SẴN trong
            // cùng nhóm luật, nên chỉ cần bỏ `.form-control`/`.form-select` đi.
            'nhập kho' => ['products/index_output.blade.php', 0],
            // 1 `.form-control` được miễn: ô chọn giả dựng bằng <div>, không có thẻ
            // component tương ứng. Nó đã mang sẵn `.ego-select` nên vẫn đúng kiểu.
            'xuất kho' => ['products/index_input.blade.php', 0, 1],
            'duyệt đơn hàng' => ['orders/approval-form.blade.php', 0],
            'hồ sơ cá nhân' => ['users/profile.blade.php', 0],
            'lịch sử sản phẩm' => ['products/history.blade.php', 0],
            'sửa nhóm sản phẩm' => ['product-categories/edit.blade.php', 0],
            'tạo nhóm sản phẩm' => ['product-categories/create.blade.php', 0],
            'bảng giá (form)' => ['price_tiers/_form.blade.php', 0],
            'phương thức thanh toán (form)' => ['payment_methods/_form.blade.php', 0],
            'thương hiệu (form)' => ['brands/_form.blade.php', 0],
            'người dùng (form)' => ['users/_form.blade.php', 0],
            'tải lên lead' => ['marketing/leads/upload.blade.php', 0],
            'danh sách lead' => ['marketing/leads/index.blade.php', 0],
            'bảng điều khiển marketing' => ['marketing/dashboard.blade.php', 0],
            'sửa chức danh' => ['hr/positions/edit.blade.php', 0],
            'tạo chức danh' => ['hr/positions/create.blade.php', 0],
            'sửa phòng ban' => ['hr/departments/edit.blade.php', 0],
            'tạo phòng ban' => ['hr/departments/create.blade.php', 0],
            'bảng điều khiển nhân sự' => ['hr/dashboard.blade.php', 0],
            'danh sách công việc' => ['tasks/index.blade.php', 0],
            'tạo phiếu trả hàng' => ['orders/returns/create.blade.php', 0],
            'danh sách tài khoản quỹ' => ['finance/accounts/index.blade.php', 0],
            'sửa đề nghị thanh toán' => ['payment_requests/edit.blade.php', 0],
            'chi tiết tiến độ marketing' => ['marketing/progress/detail.blade.php', 0],
            'KPI của tôi' => ['marketing/kpi_payroll/my.blade.php', 0],
            'danh sách bảng giá' => ['price_tiers/index.blade.php', 0],
            'danh sách phương thức thanh toán' => ['payment_methods/index.blade.php', 0],
            'tổng quan tiến độ marketing' => ['marketing/progress/index.blade.php', 0],
            'sửa kế hoạch marketing' => ['marketing/plans/edit.blade.php', 0],
            'tạo kế hoạch marketing' => ['marketing/plans/create.blade.php', 0],
            'bảng KPI marketing' => ['marketing/kpi_payroll/index.blade.php', 0],
            'quyết toán' => ['finance/settlement.blade.php', 0],
            'đối soát' => ['finance/audit.blade.php', 0],
            'danh sách thương hiệu' => ['brands/index.blade.php', 0],
            'sửa hồ sơ cá nhân' => ['users/profile-edit.blade.php', 0],
            'công việc của tôi' => ['tasks/my.blade.php', 0],
            'danh sách đề xuất' => ['proposals/index.blade.php', 0],
            'công việc tuần marketing' => ['marketing/weekly_tasks.blade.php', 0],
            'báo cáo tài chính' => ['finance/reports.blade.php', 0],
            'sửa lương kỹ thuật' => ['kythuat/luong_edit.blade.php', 0],
            // 1 ô chọn giữ `.form-select`: #customerSelect do Tom Select quản lý
            // (public/js/order-form.js gọi `new TomSelect` trên đúng ô này).
            'đơn hàng — thông tin' => ['orders/partials/order-info.blade.php', 1],
            'đơn hàng — bảng sản phẩm' => ['orders/partials/product-table.blade.php', 0],
        ];
    }

    #[DataProvider('views')]
    public function test_khong_con_lop_bootstrap_ngoai_phan_duoc_mien(
        string $view,
        int $tomSelectCount,
        int $controlCount = 0,
    ): void {
        // Bỏ chú thích Blade TRƯỚC khi rà: chú thích hay nhắc lại đúng tên thẻ đang kiểm
        // (`<x-ui.input>` trong lời giải thích) và làm guard đỏ oan — đã vấp nhiều lần.
        $source = (string) preg_replace(
            '/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path('views/'.$view))
        );

        // Bỏ khối <script> cho CẢ BA phép đếm: HTML do JS dựng lúc chạy không
        // chuyển sang component được. Trước đây `form-select` lại đếm trên nguyên
        // tệp, nên một ô chọn nằm trong chuỗi JS bị quy oan thành "ô Tom Select".
        $blade = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $source);

        preg_match_all('/class="[^"]*(?<![\w-])form-select(?![\w-])[^"]*"/', $blade, $selects);
        preg_match_all('/class="[^"]*(?<![\w-])form-control(?![\w-])[^"]*"/', $blade, $controls);
        preg_match_all('/class="[^"]*(?<![\w-])form-label(?![\w-])[^"]*"/', $blade, $labels);

        $this->assertCount($tomSelectCount, $selects[0],
            'Chỉ những ô chọn do Tom Select quản lý mới được giữ `.form-select` (xem PHPDoc).');
        $this->assertCount($controlCount, $controls[0],
            '`.form-control` phải chuyển sang <x-ui.input>, trừ phần được khai miễn tường minh.');
        $this->assertSame([], $labels[0], '`.form-label` phải chuyển sang <x-ui.label>');
    }

    #[DataProvider('views')]
    public function test_the_input_tu_dong(
        string $view,
        int $tomSelectCount,
        int $controlCount = 0,
    ): void {
        unset($tomSelectCount, $controlCount);
        // Bỏ chú thích Blade TRƯỚC khi rà: chú thích hay nhắc lại đúng tên thẻ đang kiểm
        // (`<x-ui.input>` trong lời giải thích) và làm guard đỏ oan — đã vấp nhiều lần.
        $source = (string) preg_replace(
            '/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path('views/'.$view))
        );

        /*
         * `<input>` là thẻ rỗng nên component phải TỰ ĐÓNG. Thiếu dấu `/` thì Blade
         * coi phần còn lại của template là slot và nuốt luôn `@endif` phía sau —
         * lỗi "unexpected end of file, expecting endif" đã vấp đúng kiểu này.
         */
        preg_match_all('/<x-ui\.input\b(?![^>]*as=)((?:[^>"]|"[^"]*")*)>/s', $source, $m);

        $notSelfClosed = [];

        foreach ($m[1] as $i => $attrs) {
            if (! str_ends_with(rtrim($attrs), '/')) {
                $notSelfClosed[] = 'thẻ thứ '.($i + 1);
            }
        }

        // Khai báo thẳng thay vì vòng lặp assert: view chỉ có nhãn và ô nhiều dòng
        // (như orders/approval-form) thì vòng lặp không chạy assert nào và PHPUnit
        // báo test "risky".
        $this->assertSame([], $notSelfClosed,
            "Thẻ <x-ui.input> trong $view chưa tự đóng: ".implode(', ', $notSelfClosed));
    }

    /**
     * Tệp CSS NGOÀI của cụm đề nghị thanh toán cũng không được quay lại `.form-*`.
     *
     * Trang `payment_requests/index` không tạo kiểu trong `<style>` mà bằng
     * `public/css/ego-payment-requests-enterprise.css`. Khi chuyển view sang
     * component, bộ chọn trong tệp đó đã đổi sang `.pr-input` / `.pr-label`.
     * Nếu ai đó thêm lại luật `.form-control` vào đây thì luật mới sẽ không khớp
     * phần tử nào — trông như "CSS không ăn" mà rất khó truy.
     *
     * 5 view còn lại cùng nạp tệp này (payment_requests/show,
     * advance_requests/*, settlement_requests/*) không có ô Bootstrap nào, kể cả
     * trong partial — đã kiểm khi đổi tên.
     */
    public function test_css_ngoai_cua_de_nghi_thanh_toan_khong_con_bo_chon_bootstrap(): void
    {
        $path = public_path('css/ego-payment-requests-enterprise.css');
        $css = (string) file_get_contents($path);

        // KHÔNG chặn ký tự phía trước dấu chấm: bộ chọn lớp luôn mở đầu bằng `.`
        // nhưng phía trước hoàn toàn có thể là tên thẻ. Bản đầu chặn `(?<![\w-])`
        // nên bỏ sót `textarea.form-control` — một luật CHẾT nằm im trong CSS cụm
        // kho mà test vẫn báo xanh (phát hiện 2026-09-04 khi soi tay trên server).
        preg_match_all('/[\w-]*\.form-(?:control|select|label)(?![\w-])/', $css, $found);

        $this->assertSame([], $found[0],
            "Còn bộ chọn Bootstrap trong $path — view đã chuyển sang <x-ui.*> nên\n".
            'các luật đó không khớp phần tử nào. Dùng `.pr-input` / `.pr-label`.');
    }

    /**
     * Tệp CSS của cụm kho cũng không được quay lại bộ chọn Bootstrap.
     *
     * `public/css/ego-inventory-enterprise.css` phục vụ 9 view
     * (warehouses/*, products/create|edit|index_input|index_output,
     * products/goods-receipts/index). Nhóm luật ở đó VỐN ĐÃ liệt kê
     * `.ego-input`, `.ego-select`, `.ego-textarea`, `.gr-control` bên cạnh
     * `.form-control`/`.form-select`, nên khi chuyển view chỉ cần bỏ hai bộ chọn
     * Bootstrap đi — không phải đặt tên class mới.
     *
     * 7 view còn lại của cụm không có ô Bootstrap nào, kể cả trong partial.
     */
    public function test_css_cum_kho_khong_con_bo_chon_bootstrap(): void
    {
        $path = public_path('css/ego-inventory-enterprise.css');
        $css = (string) file_get_contents($path);

        // KHÔNG chặn ký tự phía trước dấu chấm: bộ chọn lớp luôn mở đầu bằng `.`
        // nhưng phía trước hoàn toàn có thể là tên thẻ. Bản đầu chặn `(?<![\w-])`
        // nên bỏ sót `textarea.form-control` — một luật CHẾT nằm im trong CSS cụm
        // kho mà test vẫn báo xanh (phát hiện 2026-09-04 khi soi tay trên server).
        preg_match_all('/[\w-]*\.form-(?:control|select|label)(?![\w-])/', $css, $found);

        $this->assertSame([], $found[0],
            "Còn bộ chọn Bootstrap trong $path — các view đã chuyển sang <x-ui.*>\n".
            'nên luật đó không khớp phần tử nào. Dùng `.ego-input` / `.ego-select`.');
    }
}
