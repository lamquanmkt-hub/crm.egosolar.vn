<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Hợp đồng tên role trong middleware định tuyến.
 *
 * ## Vấn đề gốc
 * `role:a|b|c` của Spatie KHÔNG báo lỗi khi tên role không tồn tại — nó chỉ lặng
 * lẽ không khớp ai. Nghĩa là gõ sai tên role không gây lỗi nào lúc chạy, không
 * dòng log nào, và chỉ lộ ra khi có người phàn nàn "tôi không vào được trang".
 *
 * Rà ngày 2026-08-06: 23 tên role trong middleware, chỉ 11 tên có role thật.
 * 12 tên còn lại nằm ở 108 route. May là KHÔNG route nào bị khoá chết (mỗi route
 * đều còn ít nhất một tên thật), nên đó là vấn đề dễ gây hiểu nhầm chứ không
 * phải lỗ hổng.
 *
 * ## Vì sao GIỮ 12 tên đó thay vì xoá
 * Chủ hệ thống quyết giữ (2026-08-06): rà xoá ở 108 route có rủi ro sửa nhầm cao
 * hơn lợi ích, và có thể sau này tạo thật các role đó. Đổi lại, chúng phải được
 * KHAI BÁO tường minh trong `role_permissions.legacy_role_aliases` để:
 *   - người đọc code biết ngay tên nào là thật, tên nào là dấu vết cũ;
 *   - tên lạ thứ 13 (thường là lỗi chính tả) làm test đỏ ngay lập tức.
 *
 * ## Vì sao đối chiếu với CONFIG chứ không với bảng `roles`
 * DB test chỉ có schema, không có dữ liệu phân quyền của production, nên đọc bảng
 * `roles` trong test sẽ luôn rỗng. `protected_roles` trong config đang khớp đúng
 * 11 role thật trên production và là thứ đi cùng source code.
 * Việc đối chiếu config với DB thật do `php artisan authz:audit` làm, chạy trên
 * production.
 */
final class RoleNameContractTest extends TestCase
{
    /** Tên role thật, đi cùng source code. */
    private function canonicalRoles(): array
    {
        /** @var list<string> $roles */
        $roles = config('role_permissions.protected_roles', []);

        return $roles;
    }

    /** Tên cũ được phép còn sót lại, có khai báo tường minh. */
    private function allowedLegacyAliases(): array
    {
        /** @var array<string, string> $aliases */
        $aliases = config('role_permissions.legacy_role_aliases', []);

        return array_keys($aliases);
    }

    /**
     * Mọi tên role trong middleware phải là role thật, hoặc là tên cũ ĐÃ KHAI BÁO.
     *
     * Đây là chốt chính: nó bắt lỗi chính tả tên role — thứ mà Spatie im lặng bỏ qua.
     */
    public function test_every_role_name_in_middleware_is_declared(): void
    {
        $known = array_merge($this->canonicalRoles(), $this->allowedLegacyAliases());
        $undeclared = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($this->roleNamesOf($route) as $name) {
                if (in_array($name, $known, true)) {
                    continue;
                }

                $undeclared[$name][] = $route->methods()[0].' /'.$route->uri();
            }
        }

        ksort($undeclared);

        $lines = [];

        foreach ($undeclared as $name => $routes) {
            $lines[] = sprintf('%s  (%d route, ví dụ: %s)', $name, count($routes), $routes[0]);
        }

        $this->assertSame(
            [],
            $lines,
            "Tên role không được khai báo ở đâu cả.\n".
            "Spatie KHÔNG báo lỗi với tên lạ — nó chỉ lặng lẽ không khớp ai, nên đây thường là LỖI CHÍNH TẢ.\n".
            "Nếu là role mới: thêm vào `role_permissions.protected_roles` và tạo role trong DB.\n".
            "Nếu là dấu vết cũ: thêm vào `role_permissions.legacy_role_aliases` kèm ghi chú ý nghĩa.\n\n".
            implode("\n", $lines),
        );
    }

    /**
     * KHÔNG route nào được canh CHỈ bằng tên role không tồn tại.
     *
     * Đây mới là thứ gây hại thật: route như vậy không ai vào được (trừ nhánh dự
     * phòng theo permission của RoleWeb), mà cũng chẳng báo lỗi gì.
     */
    public function test_no_route_is_guarded_only_by_nonexistent_roles(): void
    {
        $canonical = $this->canonicalRoles();
        $lockedOut = [];

        foreach (Route::getRoutes() as $route) {
            $names = $this->roleNamesOf($route);

            if ($names === []) {
                continue;
            }

            if (array_intersect($names, $canonical) === []) {
                $lockedOut[] = sprintf(
                    '%s /%s  role:%s',
                    $route->methods()[0],
                    $route->uri(),
                    implode('|', $names),
                );
            }
        }

        sort($lockedOut);

        $this->assertSame(
            [],
            $lockedOut,
            "Route bị canh toàn bằng role KHÔNG TỒN TẠI — không ai vào được:\n".implode("\n", $lockedOut),
        );
    }

    /**
     * Danh sách tên cũ không được lẫn tên role thật.
     *
     * Nếu một tên trong đó đã trở thành role thật thì phải gỡ khỏi danh sách cũ,
     * không thì nó che mất việc tên đó nay đã có hiệu lực.
     */
    public function test_legacy_alias_list_does_not_contain_real_roles(): void
    {
        $overlap = array_values(array_intersect($this->allowedLegacyAliases(), $this->canonicalRoles()));

        sort($overlap);

        $this->assertSame(
            [],
            $overlap,
            "Tên dưới đây vừa nằm trong legacy_role_aliases vừa là role thật — gỡ khỏi danh sách cũ:\n  ".
            implode("\n  ", $overlap),
        );
    }

    /**
     * Danh sách tên cũ không được chứa tên KHÔNG còn dùng ở route nào.
     *
     * Giữ một tên đã hết dấu vết chỉ làm danh sách phình ra và khiến người đọc
     * tưởng nó còn ý nghĩa.
     */
    public function test_legacy_alias_list_has_no_stale_entries(): void
    {
        $inRoutes = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($this->roleNamesOf($route) as $name) {
                $inRoutes[$name] = true;
            }
        }

        $stale = array_values(array_filter(
            $this->allowedLegacyAliases(),
            static fn (string $alias): bool => ! isset($inRoutes[$alias]),
        ));

        sort($stale);

        $this->assertSame(
            [],
            $stale,
            "Tên cũ dưới đây không còn route nào dùng — gỡ khỏi legacy_role_aliases:\n  ".
            implode("\n  ", $stale),
        );
    }

    /** Mỗi tên cũ phải có ghi chú ý nghĩa, không được để trống. */
    public function test_every_legacy_alias_is_documented(): void
    {
        /** @var array<string, string> $aliases */
        $aliases = config('role_permissions.legacy_role_aliases', []);

        $undocumented = [];

        foreach ($aliases as $alias => $note) {
            if (trim((string) $note) === '') {
                $undocumented[] = $alias;
            }
        }

        $this->assertSame(
            [],
            $undocumented,
            "Tên cũ thiếu ghi chú ý nghĩa — không có ghi chú thì sau này không ai quyết được nên xoá hay tạo role thật:\n  ".
            implode("\n  ", $undocumented),
        );
    }

    /**
     * Tên role lấy từ middleware `role:` và `role_or_permission:`.
     *
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
                if (! str_starts_with($middleware, $prefix)) {
                    continue;
                }

                foreach (explode('|', substr($middleware, $offset)) as $name) {
                    $name = trim($name);

                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }
}
