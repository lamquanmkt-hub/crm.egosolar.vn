<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RolePermission\PageAccessService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;

/**
 * Soi phân quyền của TỪNG route, policy và gate — CHỈ ĐỌC, an toàn trên production.
 *
 * Bổ sung cho hai lệnh sẵn có:
 *   - `permissions:audit-users` soi phía NGƯỜI DÙNG (ai không role, ai bị khoá).
 *   - Lệnh này soi phía ĐỊNH TUYẾN: mỗi route được chặn bằng lớp nào, tên role
 *     và permission viết trong middleware có thật không, policy có được áp không.
 *
 * Trả lời những câu dễ tưởng đã ổn nhưng thực tế hay lệch:
 *   1. Route nào không có lớp kiểm quyền nào?
 *   2. Route nào chỉ có `auth` — nó dựa vào EnforcePageAccess hay tự kiểm?
 *   3. Tên role trong `role:a|b` có tồn tại trong DB không? (tên sai thì KHÔNG
 *      báo lỗi, chỉ lặng lẽ không khớp ai — rất khó phát hiện)
 *   4. Có route nào CHỈ được canh bằng những tên role không tồn tại? (khoá chết)
 *   5. Policy nào Gate không nhận? Policy nào chưa từng được dùng?
 *
 *   php artisan authz:audit
 *   php artisan authz:audit --routes    (liệt kê chi tiết từng route)
 */
final class AuditAuthorization extends Command
{
    protected $signature = 'authz:audit {--routes : Liệt kê chi tiết từng route}';

    protected $description = 'Soi role/permission/policy của từng route (chỉ đọc)';

    /**
     * Tiền tố hạ tầng, không phải route nghiệp vụ.
     *
     * @var list<string>
     */
    private const INFRASTRUCTURE_PREFIXES = ['_debugbar', '_ignition', 'sanctum', 'storage/'];

    public function handle(PageAccessService $pageAccess): int
    {
        $routes = $this->businessRoutes();

        $this->summariseGuards($routes, $pageAccess);
        $this->reportRoleNames($routes);
        $this->reportPermissionNames($routes);
        $this->reportPolicies();
        $this->reportGates();

        if ($this->option('routes')) {
            $this->listRoutes($routes, $pageAccess);
        } else {
            $this->newLine();
            $this->line('  Xem chi tiết từng route:  <fg=yellow>php artisan authz:audit --routes</>');
        }

        return self::SUCCESS;
    }

    /**
     * Phân loại route theo lớp chặn.
     *
     * @param  list<RoutingRoute>  $routes
     */
    private function summariseGuards(array $routes, PageAccessService $pageAccess): void
    {
        $counts = [
            'công khai (không cần đăng nhập)' => 0,
            'auth + role:' => 0,
            'auth + permission:' => 0,
            'auth + can: (policy/gate)' => 0,
            'auth + quyền page.* (EnforcePageAccess)' => 0,
            'auth + tự kiểm trong controller' => 0,
            'CHỈ auth — không thấy lớp nào' => 0,
        ];

        $ungated = [];

        foreach ($routes as $route) {
            $layer = $this->guardLayerOf($route, $pageAccess);
            $counts[$layer]++;

            if ($layer === 'CHỈ auth — không thấy lớp nào') {
                $ungated[] = $this->describe($route);
            }
        }

        $this->components->info(sprintf('%d route nghiệp vụ', count($routes)));
        $this->newLine();
        $this->line('  <options=bold>Lớp chặn</>');

        foreach ($counts as $label => $count) {
            if ($count > 0) {
                $this->line(sprintf('    %-42s %4d', $label, $count));
            }
        }

        if ($ungated !== []) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>Route không thấy lớp kiểm quyền nào</>');

            foreach ($ungated as $line) {
                $this->line('    '.$line);
            }
        }
    }

    /**
     * Tên role trong middleware so với role thật trong DB.
     *
     * @param  list<RoutingRoute>  $routes
     */
    private function reportRoleNames(array $routes): void
    {
        $inRoutes = [];

        foreach ($routes as $route) {
            foreach ($this->roleNamesOf($route) as $name) {
                $inRoutes[$name] = ($inRoutes[$name] ?? 0) + 1;
            }
        }

        $existing = $this->existingRoleNames();

        if ($existing === null) {
            $this->newLine();
            $this->line('  <fg=yellow>Không đọc được bảng roles — bỏ qua phần đối chiếu tên role.</>');

            return;
        }

        $ghosts = array_diff(array_keys($inRoutes), $existing);
        $unused = array_diff($existing, array_keys($inRoutes));

        $this->newLine();
        $this->line(sprintf(
            '  <options=bold>Role</>  %d tên trong middleware, %d role thật trong DB',
            count($inRoutes),
            count($existing),
        ));

        if ($ghosts !== []) {
            sort($ghosts);
            $this->line('    <fg=yellow>Tên KHÔNG có trong DB — kèm role THẬT đang đứng cùng trên chính route đó:</>');

            foreach ($ghosts as $name) {
                $total = $inRoutes[$name];
                $cover = $this->realRolesSharingRoutesWith($name, $routes, $existing);

                $this->line(sprintf(
                    '      %-24s %3d route  %s',
                    $name,
                    $total,
                    $cover === []
                        ? '<fg=red>KHÔNG có role thật nào — route khoá chết</>'
                        : $this->describeCoverage($cover, $total),
                ));
            }
        }

        if ($unused !== []) {
            sort($unused);
            $this->line('    <fg=yellow>Role có trong DB nhưng không route nào nhắc:</>');

            foreach ($unused as $name) {
                $this->line('      '.$name);
            }
        }

        $lockedOut = $this->routesGuardedOnlyByGhostRoles($routes, $existing);

        $this->newLine();

        if ($lockedOut === []) {
            $this->line('    <fg=green>✓ Không route nào bị khoá chết vì toàn tên role không tồn tại.</>');

            return;
        }

        $this->line('    <fg=red;options=bold>Route CHỈ canh bằng role không tồn tại — không ai vào được:</>');

        foreach ($lockedOut as $line) {
            $this->line('      '.$line);
        }
    }

    /**
     * Tên permission trong middleware so với permission thật trong DB.
     *
     * @param  list<RoutingRoute>  $routes
     */
    private function reportPermissionNames(array $routes): void
    {
        $inRoutes = [];

        foreach ($routes as $route) {
            foreach ($this->permissionNamesOf($route) as $name) {
                $inRoutes[$name] = ($inRoutes[$name] ?? 0) + 1;
            }
        }

        if ($inRoutes === []) {
            return;
        }

        $existing = $this->existingPermissionNames();

        if ($existing === null) {
            return;
        }

        $ghosts = array_diff(array_keys($inRoutes), $existing);

        $this->newLine();
        $this->line(sprintf('  <options=bold>Permission</>  %d tên trong middleware', count($inRoutes)));

        if ($ghosts === []) {
            $this->line('    <fg=green>✓ Tên nào cũng có thật trong DB.</>');

            return;
        }

        sort($ghosts);
        $this->line('    <fg=red>Tên KHÔNG có trong DB — middleware sẽ CHẶN mọi người:</>');

        foreach ($ghosts as $name) {
            $this->line(sprintf('      %-34s %3d route', $name, $inRoutes[$name]));
        }
    }

    /**
     * Policy: Gate có nhận không, và có được dùng ở đâu không.
     */
    private function reportPolicies(): void
    {
        $this->newLine();
        $this->line('  <options=bold>Policy</>');

        foreach ($this->policyFiles() as $policyClass) {
            $model = $this->modelOfPolicy($policyClass);

            if ($model === null) {
                $this->line(sprintf('    %-34s <fg=red>không tìm được model tương ứng</>', class_basename($policyClass)));

                continue;
            }

            $resolved = Gate::getPolicyFor($model);
            $matches = $resolved !== null && $resolved::class === $policyClass;

            $this->line(sprintf(
                '    %-34s %-46s %s',
                class_basename($policyClass),
                class_basename($model),
                $matches ? '<fg=green>✓ Gate áp dụng</>' : '<fg=red>✗ Gate KHÔNG áp dụng</>',
            ));
        }
    }

    /**
     * Gate tự định nghĩa (`Gate::define`) và cửa hậu (`Gate::before`).
     */
    private function reportGates(): void
    {
        $abilities = array_keys(Gate::abilities());

        $this->newLine();
        $this->line('  <options=bold>Gate tự định nghĩa</>');

        if ($abilities === []) {
            $this->line('    (không có) — mọi quyết định đi qua policy hoặc permission Spatie.');

            return;
        }

        foreach ($abilities as $ability) {
            $this->line('    '.$ability);
        }
    }

    /**
     * Bảng chi tiết từng route.
     *
     * @param  list<RoutingRoute>  $routes
     */
    private function listRoutes(array $routes, PageAccessService $pageAccess): void
    {
        $this->newLine();
        $this->line('  <options=bold>Chi tiết từng route</>');

        foreach ($routes as $route) {
            $this->line(sprintf(
                '    %-7s /%-52s %s',
                $route->methods()[0],
                mb_substr($route->uri(), 0, 52),
                $this->guardLayerOf($route, $pageAccess),
            ));
        }
    }

    /**
     * Lớp chặn thực sự của một route.
     */
    private function guardLayerOf(RoutingRoute $route, PageAccessService $pageAccess): string
    {
        $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
        $flat = implode(' ', $middleware);

        if (! str_contains($flat, 'auth')) {
            return 'công khai (không cần đăng nhập)';
        }

        if (preg_match('/\brole(_or_permission)?:/', $flat) === 1) {
            return 'auth + role:';
        }

        if (str_contains($flat, 'permission:')) {
            return 'auth + permission:';
        }

        if (preg_match('/\bcan:/', $flat) === 1) {
            return 'auth + can: (policy/gate)';
        }

        if ($this->pagePermissionFor($route, $pageAccess) !== null) {
            return 'auth + quyền page.* (EnforcePageAccess)';
        }

        if ($this->checksAuthorizationInline($route)) {
            return 'auth + tự kiểm trong controller';
        }

        return 'CHỈ auth — không thấy lớp nào';
    }

    /**
     * Quyền `page.*` mà EnforcePageAccess sẽ đòi cho route này, nếu có.
     */
    private function pagePermissionFor(RoutingRoute $route, PageAccessService $pageAccess): ?string
    {
        $uri = trim($route->uri(), '/');
        $path = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);

        try {
            $request = Request::create($path === '/' ? '/' : $path, $route->methods()[0]);

            $method = new ReflectionMethod($pageAccess, 'permissionForRequest');
            $method->setAccessible(true);

            /** @var string|null $permission */
            $permission = $method->invoke($pageAccess, $request);

            return $permission;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Controller của route có tự kiểm quyền trong thân hàm không.
     */
    private function checksAuthorizationInline(RoutingRoute $route): bool
    {
        $action = $route->getActionName();

        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return false;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return false;
        }

        try {
            $reflection = new ReflectionMethod($class, $method);
            $lines = @file((string) $reflection->getFileName());

            if ($lines === false) {
                return false;
            }

            $source = implode('', array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ));
        } catch (\Throwable) {
            return false;
        }

        return preg_match('/authorize\(|Gate::|abort_unless|abort_if|->can\(|hasRole|hasAnyRole/', $source) === 1;
    }

    /**
     * Role THẬT nào đang đứng cùng một tên role không tồn tại, và trên bao nhiêu
     * route trong số route dùng tên đó.
     *
     * Đây là cách trả lời câu "tên này đã có role thay thế sẵn chưa" bằng ĐO
     * ĐẠC thay vì suy đoán từ tên gọi: nếu một role thật phủ 100% route của tên
     * cũ thì xoá tên cũ đi không đổi ai vào được.
     *
     * @param  list<RoutingRoute>  $routes
     * @param  list<string>  $existingRoles
     * @return array<string, int> tên role thật => số route phủ, giảm dần
     */
    private function realRolesSharingRoutesWith(string $ghost, array $routes, array $existingRoles): array
    {
        $coverage = [];

        foreach ($routes as $route) {
            $names = $this->roleNamesOf($route);

            if (! in_array($ghost, $names, true)) {
                continue;
            }

            foreach (array_intersect($names, $existingRoles) as $real) {
                $coverage[$real] = ($coverage[$real] ?? 0) + 1;
            }
        }

        arsort($coverage);

        return $coverage;
    }

    /**
     * Gọn hoá phần phủ: role phủ đủ 100% ghi tên trơn, phủ một phần ghi kèm tỉ lệ.
     *
     * @param  array<string, int>  $coverage
     */
    private function describeCoverage(array $coverage, int $total): string
    {
        $full = [];
        $partial = [];

        foreach ($coverage as $role => $count) {
            if ($count === $total) {
                $full[] = $role;

                continue;
            }

            $partial[] = sprintf('%s(%d/%d)', $role, $count, $total);
        }

        if ($full === []) {
            return '<fg=yellow>phủ một phần:</> '.implode(' ', array_slice($partial, 0, 4));
        }

        return '<fg=green>phủ đủ:</> '.implode(' ', array_slice($full, 0, 4))
            .($partial !== [] ? '  + '.count($partial).' role phủ một phần' : '');
    }

    /**
     * @param  list<RoutingRoute>  $routes
     * @param  list<string>  $existingRoles
     * @return list<string>
     */
    private function routesGuardedOnlyByGhostRoles(array $routes, array $existingRoles): array
    {
        $locked = [];

        foreach ($routes as $route) {
            $names = $this->roleNamesOf($route);

            if ($names === []) {
                continue;
            }

            if (array_intersect($names, $existingRoles) === []) {
                $locked[] = $this->describe($route).'  role:'.implode('|', $names);
            }
        }

        return $locked;
    }

    /**
     * @return list<string>
     */
    private function roleNamesOf(RoutingRoute $route): array
    {
        $names = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            foreach (['role:' => 5, 'role_or_permission:' => 19] as $prefix => $offset) {
                if (str_starts_with($middleware, $prefix)) {
                    foreach (explode('|', substr($middleware, $offset)) as $name) {
                        $names[] = trim($name);
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Tên permission đòi bởi middleware `permission:` hoặc `can:` không kèm model.
     *
     * `can:update,warehouse` là gọi POLICY chứ không phải permission, nên bỏ qua.
     *
     * @return list<string>
     */
    private function permissionNamesOf(RoutingRoute $route): array
    {
        $names = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (str_starts_with($middleware, 'permission:')) {
                foreach (explode('|', substr($middleware, 11)) as $name) {
                    $names[] = trim($name);
                }
            }

            if (str_starts_with($middleware, 'can:')) {
                $parts = explode(',', substr($middleware, 4));

                if (count($parts) === 1) {
                    $names[] = trim($parts[0]);
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<RoutingRoute>
     */
    private function businessRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if ($uri === 'up') {
                continue;
            }

            foreach (self::INFRASTRUCTURE_PREFIXES as $prefix) {
                if (str_starts_with($uri, $prefix)) {
                    continue 2;
                }
            }

            $routes[] = $route;
        }

        return $routes;
    }

    /**
     * @return list<class-string>
     */
    private function policyFiles(): array
    {
        $classes = [];

        foreach (glob(app_path('Policies/*.php')) ?: [] as $file) {
            $class = 'App\\Policies\\'.basename($file, '.php');

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Model mà một policy phục vụ, đọc từ chữ ký method — đáng tin hơn đoán theo
     * tên file.
     *
     * Quy ước của policy: tham số ĐẦU luôn là người thực hiện (`User $user`),
     * tham số sau mới là đối tượng. Vì vậy chỉ xét từ tham số thứ hai trở đi —
     * có vậy `UserPolicy::update(User $user, User $model)` mới nhận ra đối tượng
     * của nó chính là User.
     *
     * @param  class-string  $policyClass
     * @return class-string|null
     */
    private function modelOfPolicy(string $policyClass): ?string
    {
        foreach ((new ReflectionClass($policyClass))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach (array_slice($method->getParameters(), 1) as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $name = $type->getName();

                if (str_starts_with($name, 'App\\Models\\')) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>|null null khi không đọc được DB
     */
    private function existingRoleNames(): ?array
    {
        try {
            return DB::table('roles')->pluck('name')->map(static fn ($n): string => (string) $n)->all();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>|null
     */
    private function existingPermissionNames(): ?array
    {
        try {
            return DB::table('permissions')->pluck('name')->map(static fn ($n): string => (string) $n)->all();
        } catch (\Throwable) {
            return null;
        }
    }

    private function describe(RoutingRoute $route): string
    {
        return sprintf('%-7s /%s', $route->methods()[0], $route->uri());
    }
}
