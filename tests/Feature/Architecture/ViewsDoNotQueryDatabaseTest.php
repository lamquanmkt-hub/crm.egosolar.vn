<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * View không tự truy vấn CSDL — và danh sách ngoại lệ chỉ được NGẮN đi.
 *
 * ## Vì sao
 * Nguyên tắc đã ghi ở `ViewComposerServiceProvider`. Thực tế đã trả giá:
 *
 * - `sites/index` chạy 4 câu truy vấn cho MỖI công trình trong vòng lặp hiển thị
 *   (42 câu cho 10 dòng, trang phân trang 20 dòng).
 * - `finance/salary` chạy một câu COUNT cho mỗi nhân viên để lấy lại con số
 *   controller đã gộp sẵn.
 * - Một khối 16 dòng đếm badge được chép qua 9 view, kết quả bị view composer ghi
 *   đè nên không hiển thị ở đâu.
 * - Khối truy vấn `companies` chép nguyên vào 3 view `sites/*`.
 *
 * Không cái nào trong số đó lộ ra khi đọc code từng tệp; phải đo mới thấy.
 *
 * ## Cách dùng test này
 * Thêm view mới vào danh sách dưới đây là SAI hướng. Dọn được view nào thì xoá
 * tên nó khỏi danh sách — test tự đỏ nếu danh sách còn tên đã dọn xong, nên nó
 * không bị bỏ quên.
 */
final class ViewsDoNotQueryDatabaseTest extends TestCase
{
    /**
     * View còn truy vấn thẳng CSDL, tính đến 2026-09-24. CHỈ ĐƯỢC NGẮN ĐI.
     *
     * @var list<string>
     */
    private const CON_TON_DONG = [
        'company_context/switcher',          // đã đánh dấu view chết, không còn được include
        'material_requests/edit',
        'orders/partials/after-sales',        // đã đánh dấu view chết: chỉ orders/show-legacy (cũng chết) @include
    ];

    #[Test]
    public function khong_co_view_moi_nao_truy_van_csdl(): void
    {
        $dangTruyVan = $this->viewDangTruyVan();
        $moi = array_values(array_diff($dangTruyVan, self::CON_TON_DONG));

        $this->assertSame([], $moi, sprintf(
            "%d view MỚI truy vấn thẳng CSDL:\n%s\n\n".
            'Đưa truy vấn ra service rồi bơm vào bằng view composer hoặc controller '.
            '(xem ViewComposerServiceProvider). Đừng thêm tên vào danh sách tồn đọng.',
            count($moi),
            implode("\n", $moi)
        ));
    }

    #[Test]
    public function danh_sach_ton_dong_khong_con_ten_thua(): void
    {
        $dangTruyVan = $this->viewDangTruyVan();
        $daDon = array_values(array_diff(self::CON_TON_DONG, $dangTruyVan));

        $this->assertSame([], $daDon, sprintf(
            "%d view trong danh sách tồn đọng NAY ĐÃ SẠCH:\n%s\n\n".
            'Xoá tên chúng khỏi CON_TON_DONG để danh sách phản ánh đúng thực tế.',
            count($daDon),
            implode("\n", $daDon)
        ));
    }

    /** @return list<string> */
    private function viewDangTruyVan(): array
    {
        $ra = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $tep) {
            if (! $tep->isFile() || ! str_ends_with($tep->getFilename(), '.blade.php')) {
                continue;
            }

            // Bỏ chú thích Blade: nhiều chú thích giải thích việc dọn có nhắc tên lệnh.
            $ma = (string) preg_replace(
                '/\{\{--.*?--\}\}/s',
                '',
                (string) file_get_contents($tep->getPathname())
            );

            // Không chỉ DB::* — model Eloquent gọi thẳng trong view cũng là truy vấn.
            // Lỗ này để lọt orders/partials/after-sales (OrderReturn::where(...)) cho tới 2026-09-25.
            $laTruyVan = preg_match('/\bDB::(?:table|select|raw|statement)\s*\(/', $ma) === 1
                || preg_match('/\\\\?App\\\\Models\\\\[A-Za-z\\\\]+::(?:where|find|all|query|first|count|pluck|latest|orderBy)\s*\(/', $ma) === 1;

            if ($laTruyVan) {
                $ra[] = str_replace(
                    [resource_path('views').'/', '.blade.php'],
                    '',
                    $tep->getPathname()
                );
            }
        }

        sort($ra);

        return $ra;
    }
}
