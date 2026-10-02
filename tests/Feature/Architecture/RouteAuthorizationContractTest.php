<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Services\RolePermission\PageAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;
use Throwable;

/**
 * Hợp đồng phân quyền: KHÔNG được xuất hiện route mới sau đăng nhập mà không có
 * lớp kiểm tra quyền nào.
 *
 * Một route được coi là có kiểm tra nếu thoả MỘT trong bốn:
 *   1. middleware `role:` / `can:` / `permission:` / `role_or_permission:`
 *   2. khớp một quyền `page.*` trong config role_permissions (middleware
 *      EnforcePageAccess sẽ chặn)
 *   3. gọi `$this->authorize()` / `Gate::` / `abort_unless` trong chính method
 *   4. dùng FormRequest có `authorize()` thật (không phải `return true`)
 *
 * Danh sách miễn trừ bên dưới là các endpoint **tự phục vụ**: ai đăng nhập cũng
 * được dùng cho chính mình (hồ sơ, thông báo, ngữ cảnh công ty, trợ lý AI).
 * Thêm route vào đây phải kèm lý do — đó là điểm để review soi.
 *
 * ⚠️ GIỚI HẠN ĐÃ BIẾT của lớp (2): `EnforcePageAccess` chỉ chặn khi
 * `pageControlEnabled($user)` = true, tức là khi role của user CÓ ít nhất một
 * quyền `page.*` (do `role_permissions.legacy_compatibility` = true). Role chưa
 * được gán quyền trang nào sẽ đi lọt, TRỪ các quyền liệt kê trong
 * `always_enforce_permissions` (hiện chỉ có `page.orders`). Mở rộng danh sách đó
 * cho các module nhạy cảm (tài chính, nhân sự, cài đặt) là quyết định nghiệp vụ.
 */
final class RouteAuthorizationContractTest extends TestCase
{
    /**
     * Endpoint tự phục vụ — cố ý mở cho mọi tài khoản đã đăng nhập.
     *
     * @var array<string, string> "METHOD uri" => lý do
     */
    private const SELF_SERVICE_ROUTES = [
        'GET profile' => 'Xem hồ sơ của chính mình',
        'GET profile/edit' => 'Sửa hồ sơ của chính mình',
        'PUT profile' => 'Sửa hồ sơ của chính mình',
        'POST profile/avatar' => 'Đổi ảnh đại diện của chính mình',
        'GET notifications' => 'Thông báo của chính mình',
        'GET notifications/json' => 'Thông báo của chính mình',
        'GET notifications/unread-count' => 'Đếm thông báo chưa đọc của chính mình',
        'POST notifications/read-all' => 'Đánh dấu đã đọc thông báo của chính mình',
        'POST notifications/{id}/read' => 'Đánh dấu đã đọc một thông báo của chính mình',
        'GET chon-cong-ty' => 'Đặt ngữ cảnh công ty cho phiên làm việc',
        'POST chon-cong-ty' => 'Đặt ngữ cảnh công ty cho phiên làm việc',
        'POST doi-cong-ty' => 'Xoá ngữ cảnh công ty của phiên làm việc',
        'GET ai-assistant/conversations' => 'Hội thoại trợ lý AI của chính mình',
        'POST ai-assistant/chat' => 'Trợ lý AI của chính mình',
        'POST push/subscribe' => 'Đăng ký nhận thông báo đẩy cho thiết bị của mình',
        'POST push/unsubscribe' => 'Huỷ đăng ký thông báo đẩy của thiết bị mình',

        // Đề nghị tạm ứng / hoàn ứng: nhân viên nào cũng được tạo và xem CỦA CHÍNH MÌNH.
        // Controller lọc theo created_by khi người xem không có quyền xem tất cả
        // (AdvanceRequestController::canViewAll), còn mọi thao tác trên một phiếu cụ thể
        // — xem, sửa, xoá, trình, duyệt — đều đã có abort_unless riêng.
        'GET advance-requests' => 'Danh sách đề nghị tạm ứng của chính mình',
        'POST advance-requests' => 'Tạo đề nghị tạm ứng cho chính mình',
        'GET settlement-requests' => 'Danh sách đề nghị hoàn ứng của chính mình',
        'POST settlement-requests' => 'Tạo đề nghị hoàn ứng cho chính mình',

        // Trang chủ sau đăng nhập: liệt kê các phòng ban NGƯỜI DÙNG ĐƯỢC VÀO
        // (DepartmentWorkspaceService::cards lọc sẵn). Vào từng phòng ban thì
        // WorkspaceController@department/@dashboard kiểm bằng Gate 'access-workspace'.
        'GET workspace' => 'Trang chủ workspace, chỉ hiện phòng ban người dùng được vào',
    ];

    /** Tiền tố route hạ tầng, không phải nghiệp vụ. */
    private const SKIP_PREFIXES = ['_debugbar', '_ignition', 'sanctum', 'telescope', 'horizon', 'livewire'];

    /** Không được có route nghiệp vụ nào thiếu hoàn toàn lớp kiểm tra quyền. */
    public function test_no_authenticated_route_is_left_ungated(): void
    {
        $ungated = [];

        foreach ($this->authenticatedRoutes() as $key => $route) {
            if (array_key_exists($key, self::SELF_SERVICE_ROUTES)) {
                continue;
            }

            if (! $this->isGated($route)) {
                $ungated[] = $key.'  ->  '.$route->getActionName();
            }
        }

        sort($ungated);

        $this->assertSame(
            [],
            $ungated,
            "Route mới KHÔNG có lớp kiểm tra quyền nào.\n".
            "Thêm middleware can:/role:, quyền page.* trong config/role_permissions.php,\n".
            "\$this->authorize() trong controller, hoặc FormRequest::authorize().\n".
            'Nếu cố ý mở cho mọi người đã đăng nhập thì khai báo vào SELF_SERVICE_ROUTES kèm lý do.',
        );
    }

    /** Danh sách miễn trừ phải luôn khớp route thật — không để rác tồn đọng. */
    public function test_self_service_allowlist_has_no_stale_entries(): void
    {
        $existing = array_keys($this->authenticatedRoutes());
        $stale = array_diff(array_keys(self::SELF_SERVICE_ROUTES), $existing);

        $this->assertSame(
            [],
            array_values($stale),
            'Route trong danh sách miễn trừ không còn tồn tại — hãy xoá khỏi SELF_SERVICE_ROUTES.',
        );
    }

    /**
     * Mọi route nghiệp vụ yêu cầu đăng nhập, đánh khoá "METHOD uri".
     *
     * @return array<string, \Illuminate\Routing\Route>
     */
    private function authenticatedRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (Str::startsWith($uri, self::SKIP_PREFIXES)) {
                continue;
            }

            $action = $route->getActionName();

            if (! str_contains($action, '@') && ! str_contains($action, 'Controller')) {
                continue;
            }

            if (! str_contains(implode(',', $route->gatherMiddleware()), 'auth')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $routes[$method.' '.$uri] = $route;
            }
        }

        return $routes;
    }

    /** Route có ít nhất một trong bốn lớp kiểm tra quyền. */
    private function isGated(\Illuminate\Routing\Route $route): bool
    {
        $middleware = implode(',', $route->gatherMiddleware());

        if (preg_match('/(^|,)(role|can|permission|role_or_permission):/', $middleware) === 1) {
            return true;
        }

        return $this->isGatedByPagePermission($route) || $this->methodChecksAuthorization($route);
    }

    /** Có quyền `page.*` nào khớp tên route hoặc đường dẫn không. */
    private function isGatedByPagePermission(\Illuminate\Routing\Route $route): bool
    {
        $definitions = app(PageAccessService::class)->definitions();
        $name = $route->getName();
        $path = '/'.ltrim($route->uri(), '/');

        foreach ($definitions as $definition) {
            foreach ($definition['routes'] ?? [] as $pattern) {
                if ($name !== null && Str::is($pattern, $name)) {
                    return true;
                }
            }

            if (in_array($path, $definition['exact_paths'] ?? [], true)) {
                return true;
            }

            foreach ($definition['path_prefixes'] ?? [] as $prefix) {
                $prefix = '/'.trim($prefix, '/');

                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Controller method (hoặc FormRequest của nó) có tự kiểm tra quyền không. */
    private function methodChecksAuthorization(\Illuminate\Routing\Route $route): bool
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return false;
        }

        [$class, $method] = explode('@', $action);

        if (! class_exists($class)) {
            return false;
        }

        try {
            $reflection = new ReflectionMethod($class, $method);
        } catch (Throwable) {
            return false;
        }

        if (preg_match('/\$this->authorize\(|Gate::|->can\(|abort_unless|abort_if/', $this->sourceOf($reflection)) === 1) {
            return true;
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($name === null || ! class_exists($name) || ! is_subclass_of($name, FormRequest::class)) {
                continue;
            }

            if ($this->formRequestAuthorizes($name)) {
                return true;
            }
        }

        return false;
    }

    /** FormRequest có `authorize()` thật (không phải mặc định `return true`). */
    private function formRequestAuthorizes(string $formRequestClass): bool
    {
        try {
            $authorize = new ReflectionMethod($formRequestClass, 'authorize');
        } catch (Throwable) {
            return false;
        }

        if ($authorize->getDeclaringClass()->getName() === FormRequest::class) {
            return false;
        }

        return preg_match('/return\s+true\s*;/', $this->sourceOf($authorize)) !== 1;
    }

    /** Mã nguồn của một method. */
    private function sourceOf(ReflectionMethod $method): string
    {
        $file = $method->getFileName();

        if ($file === false) {
            return '';
        }

        $lines = file($file) ?: [];

        return implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));
    }
}
