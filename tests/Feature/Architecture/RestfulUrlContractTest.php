<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Chốt hình dạng URL của các module đã chuyển sang REST.
 *
 * Cách chuyển: đổi URI nhưng GIỮ NGUYÊN tên route, nhờ vậy mọi `route()` trong
 * Blade tự trỏ sang URL mới. Test này khoá cả hai vế — tên còn đó, và URI đúng
 * hình dạng REST — để không ai vô tình đưa động từ trở lại đường dẫn.
 *
 * KHÔNG có lớp redirect URL cũ: đây là trang quản trị nội bộ, không bookmark
 * ngoài (chủ hệ thống đã xác nhận).
 */
final class RestfulUrlContractTest extends TestCase
{
    /**
     * Tên route => URI mong đợi, cho các module đã chuyển.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function convertedRoutes(): array
    {
        return [
            // Cài đặt phân quyền: vai trò vào ĐƯỜNG DẪN thay vì ?role=
            'quyền trang theo vai trò' => ['admin.settings.roles.pages.show', 'cai-dat/roles/{role}/quyen-trang'],
            'ghi quyền trang' => ['admin.settings.roles.pages', 'cai-dat/roles/{role}/quyen-trang'],
            'quyền menu theo vai trò' => ['admin.settings.roles.menus.show', 'cai-dat/roles/{role}/quyen-menu'],
            'quyền thao tác theo vai trò' => ['admin.settings.roles.actions.show', 'cai-dat/roles/{role}/quyen-thao-tac'],
            'nhân bản vai trò' => ['admin.settings.roles.clone', 'cai-dat/roles/{role}/ban-sao'],

            // Đơn hàng
            'xuất excel đơn hàng' => ['orders.export.excel', 'orders/exports/excel'],
            'gửi duyệt đơn' => ['orders.submit', 'orders/{id}/submit'],
            'huỷ đơn' => ['orders.cancel', 'orders/{id}/cancel'],
            'tải tệp chứng từ đơn' => ['orders.documents-ego.download', 'orders/{order}/documents-ego/{document}/download'],
            'xoá chứng từ đơn' => ['orders.documents-ego.destroy', 'orders/{order}/documents-ego/{document}'],

            // Đề nghị thanh toán
            'xuất excel ĐNTT' => ['payment_requests.export_excel', 'payment-requests/exports/excel'],
            'xuất pdf ĐNTT' => ['payment_requests.export_pdf', 'payment-requests/exports/pdf'],
            'gửi duyệt ĐNTT' => ['payment_requests.submit', 'payment-requests/{id}/submit'],
            'tải tệp đính kèm ĐNTT' => ['payment-requests.attachments-thao.download', 'payment-requests/{paymentRequest}/attachments-thao/{attachment}/download'],
            'xoá tệp đính kèm ĐNTT' => ['payment-requests.attachments-thao.destroy', 'payment-requests/{paymentRequest}/attachments-thao/{attachment}'],

            // Đề xuất
            'form tạo đề xuất' => ['de-xuat.create', 'de-xuat/create'],
            'lưu đề xuất' => ['de-xuat.store', 'de-xuat'],
            'phê duyệt đề xuất' => ['de-xuat.approve', 'de-xuat/{id}/duyet'],
            'xoá đề xuất' => ['de-xuat.destroy', 'de-xuat/{id}'],
        ];
    }

    #[DataProvider('convertedRoutes')]
    public function test_converted_route_has_expected_uri(string $name, string $expectedUri): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Mất tên route '{$name}' — Blade đang gọi qua tên này.");
        $this->assertSame($expectedUri, $route->uri());
    }

    /** Thao tác xoá phải dùng đúng động từ DELETE. */
    public function test_destroy_routes_use_delete_verb(): void
    {
        $destroyRoutes = [
            'orders.documents-ego.destroy',
            'payment-requests.attachments-thao.destroy',
            'de-xuat.destroy',
        ];

        foreach ($destroyRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Thiếu route {$name}");
            $this->assertContains(
                'DELETE',
                $route->methods(),
                "{$name} phải nhận DELETE, không được xoá bằng POST.",
            );
            $this->assertNotContains(
                'POST',
                $route->methods(),
                "{$name} không được nhận POST nữa — xoá bằng POST là sai ngữ nghĩa HTTP.",
            );
        }
    }

    /** Không còn động từ nào trong URL của ba module đã chuyển. */
    public function test_converted_modules_have_no_verbs_left_in_urls(): void
    {
        $forbidden = ['/xoa', '/luu', '/tao', '/copy', '/clone', '/export/'];
        $prefixes = ['orders', 'payment-requests', 'de-xuat', 'cai-dat'];
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();

            $inScope = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($route->uri(), $prefix)) {
                    $inScope = true;
                    break;
                }
            }

            if (! $inScope) {
                continue;
            }

            foreach ($forbidden as $verb) {
                if (str_contains($uri, $verb)) {
                    $offenders[] = $route->uri().'  ('.($route->getName() ?? 'không tên').')';
                    break;
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [
                // Endpoint xoá đặc quyền, KHÔNG nơi nào gọi — là lối thoát hiểm
                // để xoá tay một phiếu khi giao diện không cho.
                // ✅ Chủ hệ thống đã quyết GIỮ LẠI (2026-08-06). Ngoại lệ REST
                // được chấp nhận, không phải việc còn dở.
                'payment-requests/{id}/xoa-full-thao  (payment_requests.thao_full_delete)',
            ],
            $offenders,
            'Có động từ quay lại URL của module đã chuyển sang REST.',
        );
    }
}
