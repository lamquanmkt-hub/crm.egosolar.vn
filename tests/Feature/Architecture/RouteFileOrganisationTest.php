<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Hợp đồng tổ chức file định tuyến sau khi tách `routes/web.php` theo domain.
 *
 * `web.php` từng dài 2.654 dòng chứa mọi thứ. Nay nó chỉ còn danh sách require;
 * mỗi domain một file. Nhóm test này giữ cho cấu trúc đó không trôi ngược lại.
 *
 * ⚠️ THỨ TỰ ĐĂNG KÝ CÓ Ý NGHĨA: với method+URI trùng nhau, Laravel để route
 * đăng ký SAU ghi đè route trước (`RouteCollection::addToCollections()` ghi vào
 * mảng theo khoá method+URI nên lần ghi cuối thắng) — NGƯỢC với trực giác
 * "khớp cái đầu tiên", và ngược với điều tôi từng ghi ở đây.
 *
 * Bẫy đó đã gây hậu quả thật: 2 route ĐNTT đặc quyền bị che suốt, không ai biết.
 * Đã xoá 2026-08-06; hiện KHÔNG còn cặp route nào trùng method+URI. Test
 * PrivilegedPaymentRequestCharacterizationTest canh không cho chúng sống lại.
 */
final class RouteFileOrganisationTest extends TestCase
{
    /** `web.php` chỉ được chứa require, không định nghĩa route trực tiếp. */
    public function test_web_php_only_wires_domain_files(): void
    {
        $source = file_get_contents(base_path('routes/web.php'));

        $this->assertSame(
            0,
            preg_match_all('/^\s*Route::/m', $source),
            "routes/web.php phải chỉ chứa require các file domain.\n".
            'Thêm route mới vào đúng file domain (orders.php, finance.php, ...).',
        );

        $this->assertGreaterThanOrEqual(
            12,
            preg_match_all("/^require __DIR__\./m", $source),
            'Thiếu require file domain trong routes/web.php.',
        );
    }

    /** Mọi file domain được require đều tồn tại và không rỗng. */
    public function test_every_required_route_file_exists(): void
    {
        $source = file_get_contents(base_path('routes/web.php'));
        preg_match_all("/require __DIR__\.'\/([a-z_]+)\.php';/", $source, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $file) {
            $path = base_path("routes/{$file}.php");

            $this->assertFileExists($path);
            $this->assertGreaterThan(
                200,
                filesize($path),
                "routes/{$file}.php gần như rỗng — gộp vào file domain khác thay vì để lại.",
            );
        }
    }

    /** Không file định tuyến nào được phình lại quá lớn. */
    public function test_no_route_file_grows_out_of_control(): void
    {
        $oversized = [];

        foreach (glob(base_path('routes/*.php')) as $path) {
            $lines = count(file($path));

            if ($lines > 800) {
                $oversized[] = basename($path)." ({$lines} dòng)";
            }
        }

        $this->assertSame(
            [],
            $oversized,
            "File định tuyến quá lớn — tách nhỏ theo domain con.\n".
            'Mốc 800 dòng chọn theo file lớn nhất hiện tại (finance.php ~738 dòng).',
        );
    }

    /**
     * Bảng route không được đổi khi chỉ tổ chức lại file.
     *
     * Chốt tổng số route: tách file mà số này đổi nghĩa là có route bị mất hoặc
     * nhân đôi.
     *
     * Lịch sử con số này:
     * - 745: sau khi tách web.php theo domain (2026-08-05)
     * - 762: +17 route của Project Workflow V2 (du-an/{site}/quy-trinh/*,
     *   du-an/{site}/vat-tu/de-xuat/*) khi merge nhánh feature/project-workflow-v2
     * - 765: module Cài đặt chuyển sang REST — mỗi màn phân quyền có thêm dạng
     *   /cai-dat/roles/{role}/quyen-* (GET), bỏ định danh bằng ?role=
     * - 752: gỡ 9 route trỏ tới method KHÔNG TỒN TẠI trong controller (bấm vào
     *   là 500, không nơi nào gọi) — xem RouteControllerResolutionTest
     * - 748: xoá 2 route ĐNTT đặc quyền bị che (PATCH|PUT và POST|DELETE trên
     *   /payment-requests/{id}) — chúng chưa bao giờ chạy, tính năng thật nằm ở
     *   PaymentRequestController
     * - 823 (2026-09-02): +75 route của các tính năng thêm từ 2026-08-06. Đã đối
     *   chiếu `git diff ae0cfa1..HEAD -- routes/`: KHÔNG file nào mất route, chỉ
     *   thêm — finance +39 (đề nghị tạm ứng, hoàn ứng, cấu hình phiếu lương, ngân
     *   sách, tài khoản), technical +14, project_unified +9, projects +7, hr +3,
     *   cùng hai file mới warehouse_supplier_debts.php và workspace.php
     * - 835 (2026-09-02, cùng ngày): +12 route KHÔI PHỤC, không phải tính năng mới —
     *   11 route cụm marketing budget/metrics/campaigns (routes/tasks.php) và 1 route
     *   orders.my-orders. Chúng bị đánh rơi ngày 2026-05-06 khi khôi phục web.php từ
     *   bản cũ; 5 route budget lấy nguyên văn từ routes/web.phpbk2 ở commit 8a2fa03,
     *   4 route còn lại (budget.clone_next, metrics.edit/update/destroy) chưa từng
     *   được khai dù view vẫn gọi và controller vẫn có method.
     * - 841 (2026-09-03): +6 route cụm "yêu cầu sửa chấm công"
     *   (attendance-corrections index/store/approve/reject + phụ trợ). Tính năng này
     *   được phát triển TRỰC TIẾP TRÊN SERVER rồi mới đưa về git, xem commit kèm.
     * - 866 (2026-09-04): +25 route cụm "quà tặng" (hr/gifts: danh mục, kho, phiếu
     *   nhận, đề nghị tặng, báo cáo). LẠI là tính năng viết TRỰC TIẾP TRÊN SERVER —
     *   phát hiện khi rà `git status` trước lúc deploy, kèm 1 migration ĐÃ CHẠY trên
     *   production. Đưa về git ngày 2026-09-04.
     */
    public function test_route_table_size_is_stable(): void
    {
        $count = 0;

        foreach (Route::getRoutes() as $route) {
            $count += count(array_diff($route->methods(), ['HEAD']));
        }

        $this->assertSame(
            866,
            $count,
            "Tổng số route đã đổi.\n".
            'Nếu là chủ ý (thêm/bớt route) thì cập nhật con số này; nếu không thì có route bị mất khi tách file.',
        );
    }
}
