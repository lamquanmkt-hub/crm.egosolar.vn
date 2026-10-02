<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Mọi route phải trỏ tới một hành động THẬT SỰ TỒN TẠI trong controller.
 *
 * ## Vì sao cần test này
 * Laravel không kiểm tra điều đó lúc nạp route. Route vẫn đăng ký được, `route()`
 * trong Blade vẫn sinh ra URL đẹp, `route:list` vẫn liệt kê bình thường — chỉ đến
 * khi có người BẤM vào thì `Controller::__call()` mới ném BadMethodCallException
 * và trả 500. Nghĩa là lỗi này đi thẳng ra production mà không gì cản.
 *
 * Ngày 2026-08-05 hệ thống có 12 route như vậy:
 *  - 9 route chết nằm sẵn từ trước (không ai gọi) → đã gỡ bỏ;
 *  - 1 nút "Xoá file" trong lịch nội dung marketing vẫn hiện cho người dùng bấm,
 *    trỏ vào `deleteFile()` chưa từng được viết → đã bổ sung method;
 *  - 3 route quản lý vai trò do CHÍNH tôi làm hỏng cùng ngày, khi đổi sang
 *    `Route::apiResource()` — nó trỏ cứng vào store/update/destroy trong khi
 *    controller đặt tên là storeRole/updateRole/destroyRole.
 *
 * Cái cuối là lý do test này tồn tại: một thao tác dọn dẹp trông vô hại đã làm
 * hỏng chức năng tạo/sửa/xoá vai trò trên production, và không một test nào
 * trong 199 test lúc đó phát hiện ra.
 */
final class RouteControllerResolutionTest extends TestCase
{
    /** Route trỏ tới class controller không tồn tại. */
    public function test_every_route_points_to_an_existing_controller_class(): void
    {
        $broken = [];

        foreach ($this->controllerRoutes() as $route) {
            [$class] = $this->actionOf($route);

            if (! class_exists($class)) {
                $broken[] = $this->describe($route)." -> class {$class} không tồn tại";
            }
        }

        $this->assertSame([], $broken, "Route trỏ tới controller không tồn tại:\n".implode("\n", $broken));
    }

    /**
     * Route trỏ tới method không tồn tại — đây là lỗi 500 chờ sẵn.
     *
     * Mọi controller đều kế thừa `Illuminate\Routing\Controller::__call()`,
     * nên `method_exists()` trả false là chắc chắn hỏng khi chạy thật.
     */
    public function test_every_route_points_to_an_existing_controller_method(): void
    {
        $broken = [];

        foreach ($this->controllerRoutes() as $route) {
            [$class, $method] = $this->actionOf($route);

            if (! class_exists($class)) {
                continue; // đã báo ở test trên
            }

            if (! method_exists($class, $method)) {
                $broken[] = $this->describe($route)." -> {$class}::{$method}() không tồn tại";
            }
        }

        $this->assertSame(
            [],
            $broken,
            "Route trỏ tới method không tồn tại (bấm vào là 500):\n".implode("\n", $broken),
        );
    }

    /**
     * Hành động của controller phải là `public`.
     *
     * Method `protected`/`private` cũng rơi vào `__call()` y như khi không tồn tại,
     * nên hỏng theo đúng một kiểu nhưng khó thấy hơn.
     */
    public function test_every_route_action_is_publicly_callable(): void
    {
        $broken = [];

        foreach ($this->controllerRoutes() as $route) {
            [$class, $method] = $this->actionOf($route);

            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }

            $reflection = new ReflectionMethod($class, $method);

            if (! $reflection->isPublic()) {
                $visibility = $reflection->isPrivate() ? 'private' : 'protected';
                $broken[] = $this->describe($route)." -> {$class}::{$method}() đang {$visibility}";
            }

            if ($reflection->isStatic()) {
                $broken[] = $this->describe($route)." -> {$class}::{$method}() đang static";
            }
        }

        $this->assertSame([], $broken, "Hành động không gọi được qua HTTP:\n".implode("\n", $broken));
    }

    /**
     * View MỒ CÔI: controller có render, nhưng KHÔNG route nào trỏ tới controller
     * đó nên người dùng không vào được. Chúng gọi tên route đã bị xoá từ lâu.
     *
     * Không xoá vội trong đợt này vì đó là quyết định của chủ hệ thống, nhưng
     * cũng không để chúng làm test đỏ vĩnh viễn. Liệt kê ở đây để nhìn thấy được.
     *
     * @var list<string>
     */
    private const ORPHAN_VIEWS = [
        // Màn phân quyền đời cũ; bản đang dùng là admin/settings/index.blade.php.
        'admin/role-permissions/index.blade.php',
        // Không route nào trỏ tới MarketingPerformanceController.
        // (budget, budget_edit, dashboard đã được gỡ khỏi đây ngày 2026-09-02 —
        // cụm marketing budget đã khôi phục route, xem routes/tasks.php.)
        'marketing/performance.blade.php',
        // Không route nào trỏ tới SiteQuoteController.
        'sites/quote.blade.php',
        // Trang chào mặc định của Laravel, không controller nào render.
        'welcome.blade.php',
    ];

    /**
     * Mọi tên route mà Blade gọi qua `route()` đều phải tồn tại.
     *
     * Ngược chiều với các test trên: ở đây bắt `RouteNotFoundException` — trang
     * vỡ ngay khi render, không cần ai bấm.
     *
     * Hai thứ CỐ Ý không tính là lỗi:
     *  1. `request()->route('employee')` — đó là đọc THAM SỐ của route hiện tại,
     *     không phải tên route. Bắt nhầm kiểu này làm test kêu oan.
     *  2. Tên nằm trong `@if (Route::has('...'))` — đây là cách Laravel khuyến
     *     nghị để nút chỉ hiện khi route tồn tại, tức là đã xử lý đúng rồi.
     */
    public function test_route_names_used_in_views_all_exist(): void
    {
        $missing = [];
        $viewPath = resource_path('views');

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewPath));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace($viewPath.'/', '', $file->getPathname());

            if (in_array($relative, self::ORPHAN_VIEWS, true)) {
                continue;
            }

            $source = @file_get_contents($file->getPathname());

            if ($source === false) {
                continue;
            }

            // Tên đã được bọc trong Route::has(...) thì bỏ qua.
            preg_match_all("/Route::has\(\s*'([a-zA-Z0-9_.\-]+)'/", $source, $guarded);
            $guardedNames = array_flip($guarded[1]);

            // `(?<!->)` loại `request()->route('thamSo')`.
            preg_match_all("/(?<!->)\broute\(\s*'([a-zA-Z0-9_.\-]+)'/", $source, $matches);

            foreach (array_unique($matches[1]) as $name) {
                if (Route::has($name) || isset($guardedNames[$name])) {
                    continue;
                }

                $missing[] = $name.'  (gọi trong '.$relative.')';
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Blade gọi tên route không tồn tại — trang sẽ vỡ khi render:\n".implode("\n", $missing),
        );
    }

    /**
     * Danh sách view mồ côi phải đúng: file còn tồn tại và vẫn thật sự mồ côi.
     *
     * Nếu ai đó nối route cho một view trong danh sách, test này nhắc gỡ nó ra
     * khỏi ORPHAN_VIEWS để nó quay lại được kiểm tra thật.
     */
    public function test_orphan_view_list_is_accurate(): void
    {
        $stale = [];

        foreach (self::ORPHAN_VIEWS as $relative) {
            $path = resource_path('views/'.$relative);

            if (! is_file($path)) {
                $stale[] = $relative.' — file đã bị xoá, gỡ khỏi ORPHAN_VIEWS';

                continue;
            }

            $source = (string) file_get_contents($path);
            preg_match_all("/(?<!->)\broute\(\s*'([a-zA-Z0-9_.\-]+)'/", $source, $matches);

            $broken = array_filter(array_unique($matches[1]), fn (string $n): bool => ! Route::has($n));

            if ($broken === []) {
                $stale[] = $relative.' — không còn tên route hỏng nào, gỡ khỏi ORPHAN_VIEWS';
            }
        }

        $this->assertSame([], $stale, "Danh sách view mồ côi đã lỗi thời:\n".implode("\n", $stale));
    }

    /**
     * Các route có controller (bỏ qua closure và route của thư viện ngoài).
     *
     * @return list<RoutingRoute>
     */
    private function controllerRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();

            if (isset($action['uses']) && $action['uses'] instanceof \Closure) {
                continue;
            }

            if ($route->getActionName() === 'Closure') {
                continue;
            }

            // Route do gói ngoài đăng ký (debugbar, ignition...) không thuộc phạm vi.
            if (! str_starts_with($route->getActionName(), 'App\\')) {
                continue;
            }

            $routes[] = $route;
        }

        return $routes;
    }

    /**
     * Tách hành động thành [class, method]; controller invokable dùng `__invoke`.
     *
     * @return array{0: string, 1: string}
     */
    private function actionOf(RoutingRoute $route): array
    {
        $action = $route->getActionName();

        if (str_contains($action, '@')) {
            /** @var array{0: string, 1: string} $parts */
            $parts = explode('@', $action, 2);

            return $parts;
        }

        return [$action, '__invoke'];
    }

    private function describe(RoutingRoute $route): string
    {
        return implode('|', $route->methods()).' /'.$route->uri()
            .'  ['.($route->getName() ?? 'không tên').']';
    }
}
