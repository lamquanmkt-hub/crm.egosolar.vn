<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Số badge "chờ duyệt" chỉ được tính ở MỘT nơi.
 *
 * ## Chuyện đã xảy ra
 * Cùng một khối 16 dòng được chép vào **9 view**, tự chạy lại hai câu COUNT trên
 * `crm_order_approvals` và `material_requests` rồi nuốt lỗi bằng
 * `catch (\Throwable)`. Nhưng badge nằm trong `partials/sidebar`, mà sidebar nhận
 * giá trị từ view composer — composer chạy sau và GHI ĐÈ, nên toàn bộ phần tính
 * trong view không hiển thị ở đâu cả.
 *
 * Đo được: trang có khối đó chạy 2 câu/bảng, trang không có chạy 1. Bỏ khối đi
 * thì HTML giống hệt từng dòng — đúng nghĩa chạy phí.
 *
 * Nguồn duy nhất bây giờ: `App\Services\System\SidebarStatusService`.
 */
final class BadgeCountsNotDuplicatedTest extends TestCase
{
    /** Chỉ nơi này được phép đếm badge. */
    private const NGUON_DUY_NHAT = 'app/Services/System/SidebarStatusService.php';

    #[Test]
    public function khong_view_nao_tu_dem_badge(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            $noiDung = (string) file_get_contents($duongDan);

            // Bỏ chú thích Blade: chính chú thích giải thích chuyện này có nhắc tên biến.
            $ma = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $noiDung);

            if (preg_match('/\$egoPendingOrdersCount\s*=(?!=)/', $ma) === 1) {
                $viPham[] = str_replace(base_path().'/', '', $duongDan);
            }
        }

        $this->assertSame([], $viPham, sprintf(
            "%d view tự tính lại số badge:\n%s\n\n".
            'Badge do %s cấp cho partials.sidebar qua view composer. Tính lại trong '.
            'view chỉ tốn truy vấn, vì composer chạy sau và ghi đè.',
            count($viPham),
            implode("\n", $viPham),
            self::NGUON_DUY_NHAT
        ));
    }

    #[Test]
    public function chi_mot_noi_truy_van_bang_duyet_don(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            $ma = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($duongDan));

            if (str_contains($ma, 'crm_order_approvals')) {
                $viPham[] = str_replace(base_path().'/', '', $duongDan);
            }
        }

        $this->assertSame([], $viPham,
            "View không được truy vấn crm_order_approvals:\n".implode("\n", $viPham));

        $this->assertFileExists(base_path(self::NGUON_DUY_NHAT));
    }
}
