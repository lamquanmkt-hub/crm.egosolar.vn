<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Mọi tham chiếu `\App\...::class` viết bằng tên đầy đủ phải trỏ tới lớp có thật.
 *
 * ## Vì sao cần lưới này
 * Tên lớp viết đầy đủ trong thân method hoặc trong Blade KHÔNG được PHP kiểm lúc
 * biên dịch — chỉ nổ lúc chạy, và trong repo này thường là nổ vào một
 * `catch (\Throwable)` rồi im lặng.
 *
 * Đã dính hai lần, cùng một nguyên nhân là đổi namespace mà không rà chỗ dùng:
 *
 * - `app(\App\Services\CommissionEngineService::class)` trong trang hoa hồng —
 *   lớp chuyển sang `App\Services\CRM\Commission\` ngày 2026-07-20. `catch` nuốt
 *   `BindingResolutionException`, trang hiện mọi con số bằng 0 suốt 6 tuần.
 * - `@can('create', \App\Models\CRM\Order::class)` trong sidebar — lớp thật là
 *   `App\Models\CRM\Orders\Order`. Chỗ này chưa gây hại vì Laravel đoán policy
 *   theo TÊN CƠ SỞ nên vẫn ra `OrderPolicy`, nhưng đó là may chứ không phải đúng.
 */
final class ClassReferenceResolutionTest extends TestCase
{
    /** Nơi có thể viết tên lớp đầy đủ mà PHP không kiểm hộ. */
    private const SCAN_DIRS = ['app', 'routes', 'config', 'resources/views'];

    public function test_every_fully_qualified_app_class_reference_resolves(): void
    {
        $broken = [];

        foreach ($this->references() as $class => $files) {
            if ($this->exists($class)) {
                continue;
            }

            $broken[] = sprintf('%s  (dùng ở %s)', $class, implode(', ', array_unique($files)));
        }

        $this->assertSame([], $broken, "Tham chiếu tới lớp không tồn tại.\n".
            "Thường là do đổi namespace mà quên rà chỗ dùng. Những chỗ này KHÔNG nổ lúc\n".
            "biên dịch, và trong repo này hay bị `catch (\\Throwable)` nuốt mất:\n\n".
            implode("\n", $broken));
    }

    /** @return array<string, list<string>> tên lớp => các file có nhắc tới */
    private function references(): array
    {
        $found = [];

        foreach (self::SCAN_DIRS as $dir) {
            $path = base_path($dir);

            if (! is_dir($path)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                preg_match_all('/\\\\(App\\\\[A-Za-z0-9_\\\\]+)::class/', $source, $matches);

                foreach ($matches[1] as $class) {
                    $found[$class][] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        return $found;
    }

    private function exists(string $class): bool
    {
        return class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
    }
}
