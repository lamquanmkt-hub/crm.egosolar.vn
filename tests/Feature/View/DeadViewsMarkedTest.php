<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Danh sách view CHẾT đã đánh dấu — và dấu đó phải luôn khớp thực tế.
 *
 * ## Vì sao đánh dấu thay vì xoá
 * Xoá code là quyết định của chủ dự án. Ở đây chỉ ghi lại kết quả rà soát ngày
 * 2026-09-04 để lần sau không phải rà lại từ đầu, và để chặn hai hướng trôi:
 *
 * 1. View được đấu vào route/@include mà dấu vẫn còn -> dấu nói dối.
 * 2. Ai đó bỏ dấu nhưng view vẫn chết -> mất công rà lại.
 *
 * ## Cách rà (làm lại được)
 * Tìm chuỗi 'ten.view' và 'ten/view' trong app/, routes/, resources/views/.
 * Đã kiểm cả `view()` gọi bằng biến: hai chỗ duy nhất trong dự án
 * (System\DashboardController, System\RoleHomeController) đều truyền chuỗi cố
 * định 'dashboard.index'.
 */
final class DeadViewsMarkedTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function deadViews(): array
    {
        return [
            'đơn hàng bản cũ' => ['orders/show-legacy'],
            'form sản phẩm (mồ côi)' => ['products/_form'],
            'hiệu suất marketing' => ['marketing/performance'],
            'bảng tồn kho (mồ côi)' => ['products/partials/_stock-table'],
            'modal serial (mồ côi)' => ['products/partials/_serial-modal'],
            'modal media (mồ côi)' => ['media/modal'],
            'báo cáo ads bản số ít (mồ côi)' => ['marketing/report/ads'],
            'đơn làm online (route chỉ redirect)' => ['hr/online-work/create'],
            'công việc tuần bản cũ (mồ côi)' => ['marketing/weekly_tasks'],
            'hộp đổi trả của đơn hàng bản cũ' => ['orders/partials/after-sales'],
            'sơ đồ tổ chức chưa nối dây' => ['hr/employees/org-chart'],
            'kế hoạch marketing bản enterprise (mồ côi)' => ['marketing/plans/show_enterprise'],
            'tổng quan kế hoạch (route trỏ sang index)' => ['marketing/plans/overview'],
            'kế hoạch ads (chỗ giữ chỗ)' => ['marketing/plans/ads'],
            'kế hoạch email (chỗ giữ chỗ)' => ['marketing/plans/email'],
            'kế hoạch seo (chỗ giữ chỗ)' => ['marketing/plans/seo'],
        ];
    }

    #[DataProvider('deadViews')]
    public function test_view_chet_van_khong_ai_tham_chieu_va_van_con_dau(string $view): void
    {
        $path = resource_path('views/'.$view.'.blade.php');

        $this->assertFileExists($path,
            'View đã bị xoá — hãy bỏ nó khỏi danh sách trong '.self::class);

        $this->assertStringContainsString('EGO_VIEW_CHET', (string) file_get_contents($path),
            "Thiếu dấu EGO_VIEW_CHET ở $view.\n".
            'Nếu view này đã được dùng lại thì hãy bỏ nó khỏi danh sách, đừng thêm dấu.');

        $referrers = $this->referrersOf($view);

        $this->assertSame([], $referrers,
            "$view NAY ĐÃ ĐƯỢC DÙNG nhưng vẫn mang dấu \"view chết\":\n".
            implode("\n", $referrers)."\n\n".
            'Hãy xoá khối {{-- EGO_VIEW_CHET ... --}} trong view và bỏ nó khỏi danh sách này.');
    }

    /**
     * Nơi nào nhắc tới view — trừ chính nó, và trừ controller đã xác định là
     * không có route (trường hợp marketing/performance).
     *
     * @return list<string>
     */
    private function referrersOf(string $view): array
    {
        $needles = [
            "'".str_replace('/', '.', $view)."'",
            '"'.str_replace('/', '.', $view).'"',
            "'".$view."'",
            '"'.$view.'"',
        ];

        $self = resource_path('views/'.$view.'.blade.php');
        $skip = app_path('Http/Controllers/Marketing/MarketingPerformanceController.php');

        // Người gọi mà BẢN THÂN cũng là view chết thì không tính — "chết bắc cầu". Trước đây
        // chỉ có $skip đóng cứng cho một controller; tổng quát hoá để partial chỉ được
        // @include từ một view chết cũng đánh dấu được (orders/partials/after-sales,
        // chỉ orders/show-legacy gọi tới).
        $deadPaths = [];
        foreach (self::deadViews() as $bo) {
            $deadPaths[] = resource_path('views/'.$bo[0].'.blade.php');
        }

        $found = [];

        foreach ([app_path(), base_path('routes'), resource_path('views')] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getPathname() === $self || $file->getPathname() === $skip) {
                    continue;
                }

                if (in_array($file->getPathname(), $deadPaths, true)) {
                    continue;
                }

                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                foreach ($needles as $needle) {
                    if ($this->mentionsAsView($source, $needle)) {
                        $found[] = str_replace(base_path().'/', '', $file->getPathname()).' -> '.$needle;
                        break;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Chuỗi có xuất hiện với vai trò TÊN VIEW không.
     *
     * Tên route và tên view dùng chung dạng chấm nên trùng nhau được:
     * `marketing/report/ads` là view chết, còn `route('marketing.report.ads')` là
     * route SỐNG nhưng trỏ sang view khác (`marketing.reports.ads`). Đếm cả lượt
     * gọi route thì không view nào bị nhận ra là chết được nữa.
     */
    private function mentionsAsView(string $source, string $needle): bool
    {
        $offset = 0;

        while (($at = strpos($source, $needle, $offset)) !== false) {
            $offset = $at + 1;
            $truoc = substr($source, max(0, $at - 24), min(24, $at));

            if (preg_match('/(?:^|[^\w])route\(\s*$/', $truoc) === 1) {
                continue; // là tên route, không phải tên view
            }

            return true;
        }

        return false;
    }

    /**
     * Controller của marketing/performance vẫn không có route nào.
     *
     * Đây là mắt xích khiến view đó chết; nếu ai khai route cho nó thì phải xem
     * lại cả `route('marketing.metrics')` mà view đang gọi (route không tồn tại,
     * và view đích `marketing.metrics` đã bị xoá từ commit 7a7f538).
     */
    public function test_controller_hieu_suat_marketing_van_khong_co_route(): void
    {
        $routed = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains((string) $route->getActionName(), 'MarketingPerformanceController'))
            ->map(fn ($route): string => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $routed,
            "MarketingPerformanceController NAY ĐÃ CÓ ROUTE:\n".implode("\n", $routed)."\n\n".
            "Trước khi mở trang, phải xử lý route('marketing.metrics') mà view đang gọi:\n".
            'route đó không tồn tại và view đích đã bị xoá ở commit 7a7f538.');
    }
}
