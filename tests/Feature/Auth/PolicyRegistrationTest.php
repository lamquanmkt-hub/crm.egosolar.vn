<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Mọi policy trong `app/Policies` phải thật sự được Gate áp dụng.
 *
 * ## Vì sao cần
 * Dự án KHÔNG khai báo policy tường minh mà dựa vào cơ chế tự dò của Laravel
 * (`App\Models\...\Foo` -> `App\Policies\FooPolicy`). Cách đó chạy tốt nhưng
 * IM LẶNG khi hỏng: đổi tên model, dời model sang namespace khác, hay đặt policy
 * sai tên đều làm Gate không tìm ra policy — và khi không có policy, mọi lời gọi
 * `$this->authorize()` sẽ TỪ CHỐI, còn middleware `can:` sẽ chặn sạch. Không có
 * cảnh báo nào lúc khởi động.
 *
 * Chuyện suýt xảy ra: `app/Providers/AuthServiceProvider.php` khai báo 3 policy
 * nhưng file đó KHÔNG nằm trong `bootstrap/providers.php` nên chưa bao giờ được
 * nạp. Ba khai báo ấy vô tác dụng suốt; hệ thống chạy được là nhờ cơ chế tự dò.
 * Đã gỡ file gây hiểu nhầm đó (2026-08-06) và thay bằng test này.
 *
 * Xem thêm: `php artisan authz:audit`.
 */
final class PolicyRegistrationTest extends TestCase
{
    /** Mỗi policy phải được Gate chọn cho đúng model của nó. */
    public function test_every_policy_is_applied_by_the_gate(): void
    {
        $broken = [];
        $checked = 0;

        foreach ($this->policyClasses() as $policyClass) {
            $model = $this->modelOf($policyClass);

            if ($model === null) {
                $broken[] = class_basename($policyClass).' — không suy ra được model từ chữ ký method';

                continue;
            }

            $resolved = Gate::getPolicyFor($model);
            $checked++;

            if ($resolved === null) {
                $broken[] = class_basename($policyClass)." — Gate KHÔNG tìm thấy policy nào cho {$model}";

                continue;
            }

            if ($resolved::class !== $policyClass) {
                $broken[] = sprintf(
                    '%s — Gate đang dùng %s cho %s',
                    class_basename($policyClass),
                    class_basename($resolved),
                    $model,
                );
            }
        }

        $this->assertSame(
            [],
            $broken,
            "Policy không được áp dụng (mọi authorize() liên quan sẽ TỪ CHỐI):\n".implode("\n", $broken),
        );

        $this->assertGreaterThan(0, $checked, 'Không tìm thấy policy nào để kiểm tra.');
    }

    /**
     * Không được có provider "ma" khai báo policy mà không được nạp.
     *
     * Một file như vậy trông rất giống nơi để đăng ký policy, nên người sau sẽ
     * thêm mapping vào đó rồi mất hàng giờ tìm hiểu vì sao không ăn.
     */
    public function test_no_unloaded_provider_pretends_to_register_policies(): void
    {
        $loaded = require base_path('bootstrap/providers.php');
        $files = glob(app_path('Providers/*.php')) ?: [];

        $this->assertNotEmpty($files, 'Không quét được thư mục Providers — test này đang không kiểm gì cả.');

        foreach ($files as $file) {
            $class = 'App\\Providers\\'.basename($file, '.php');

            if (in_array($class, $loaded, true)) {
                continue;
            }

            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/\$policies\s*=|Gate::policy\(/',
                $source,
                $class.' khai báo policy nhưng KHÔNG nằm trong bootstrap/providers.php — '.
                'khai báo đó không có tác dụng. Hoặc nạp provider, hoặc bỏ khai báo đi.',
            );
        }
    }

    /**
     * @return list<class-string>
     */
    private function policyClasses(): array
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
     * Model của policy: tham số đầu là người thực hiện, tham số sau là đối tượng.
     *
     * @param  class-string  $policyClass
     * @return class-string|null
     */
    private function modelOf(string $policyClass): ?string
    {
        foreach ((new ReflectionClass($policyClass))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach (array_slice($method->getParameters(), 1) as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && str_starts_with($type->getName(), 'App\\Models\\')) {
                    return $type->getName();
                }
            }
        }

        return null;
    }
}
