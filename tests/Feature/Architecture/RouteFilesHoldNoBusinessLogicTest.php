<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * File định tuyến chỉ được KHAI BÁO đường đi, không được chứa logic nghiệp vụ.
 *
 * ## Vì sao cần
 * Tính đến 2026-08-05, `routes/` chứa 451 dòng logic controller viết thẳng dưới
 * dạng closure — nhiều nhất là `finance.php` (318 dòng, riêng thân hàm xoá ĐNTT
 * bị chép nguyên văn 3 lần) và `projects.php` (98 dòng).
 *
 * Code nằm trong file route thì:
 *  - không có class để tiêm phụ thuộc, phải gọi `app(...)` giữa chừng;
 *  - không unit test được, chỉ chạm tới được qua HTTP;
 *  - không ai nghĩ tới khi đọc `app/Http/Controllers`, nên bị sửa trùng lặp;
 *  - `route:cache` phải bỏ qua (closure không serialize được) nếu còn dùng `use`.
 *
 * ## Ranh giới: closure nào VẪN được phép
 * Không phải mọi closure đều là mùi code. Ba việc dưới đây là việc của ĐỊNH
 * TUYẾN, viết ra controller riêng chỉ làm rối:
 *  - chuyển hướng URL cũ sang route mới (`redirect()->route(...)`);
 *  - trả một view tĩnh (nên dùng `Route::view()` cho rõ nghĩa);
 *  - trả một chuỗi/JSON cố định (trang giữ chỗ).
 *
 * Test này vì vậy không cấm closure — nó chặn closure DÀI, tức chỗ đã bắt đầu
 * chứa nghiệp vụ.
 */
final class RouteFilesHoldNoBusinessLogicTest extends TestCase
{
    /**
     * Số dòng tối đa cho một closure trong file định tuyến.
     *
     * Đủ cho chuyển hướng nhiều dòng, không đủ cho một hàm nghiệp vụ.
     */
    private const MAX_CLOSURE_LINES = 8;

    /** Không closure nào trong routes/ được dài quá ngưỡng. */
    public function test_no_route_closure_contains_business_logic(): void
    {
        $offenders = [];

        foreach (glob(base_path('routes/*.php')) as $path) {
            foreach ($this->closureLengthsIn($path) as [$line, $length]) {
                if ($length > self::MAX_CLOSURE_LINES) {
                    $offenders[] = sprintf('%s:%d — closure %d dòng', basename($path), $line, $length);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Logic nghiệp vụ đang nằm trong file định tuyến.\n".
            "Chuyển sang controller/service; file route chỉ khai báo đường đi.\n".
            'Closure ngắn (chuyển hướng, view tĩnh) vẫn được phép.',
        );
    }

    /**
     * Không được truy vấn DB trực tiếp trong file định tuyến.
     *
     * Bắt riêng vì một closure ngắn vẫn có thể lén một câu `DB::table(...)`.
     */
    public function test_route_files_do_not_query_the_database(): void
    {
        $offenders = [];

        foreach (glob(base_path('routes/*.php')) as $path) {
            foreach (file($path) as $index => $line) {
                if (preg_match('/^\s*(\/\/|\*|\/\*)/', $line)) {
                    continue; // chú thích thì bỏ qua
                }

                if (preg_match('/\b(DB::table|DB::transaction|Schema::hasTable|Schema::hasColumn)\b/', $line)) {
                    $offenders[] = sprintf('%s:%d — %s', basename($path), $index + 1, trim($line));
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "File định tuyến đang truy vấn DB trực tiếp:\n".implode("\n", $offenders),
        );
    }

    /**
     * Route dùng closure phải là loại được phép: chuyển hướng, view tĩnh, hoặc
     * hằng số. Bảng dưới đây liệt kê chúng để ai thêm closure mới phải giải trình.
     */
    public function test_remaining_closures_are_only_redirects_or_static_pages(): void
    {
        $unexpected = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();

            $isClosure = ($action['uses'] ?? null) instanceof \Closure
                || $route->getActionName() === 'Closure';

            if (! $isClosure) {
                continue;
            }

            $source = $this->sourceOfClosure($route);

            if ($source === null) {
                continue;
            }

            $allowed = str_contains($source, 'redirect(')
                || str_contains($source, 'view(')
                || preg_match("/=>\s*'[^']*'/", $source) === 1
                || str_contains($source, 'Storage::')
                || str_contains($source, 'response(');

            if (! $allowed) {
                $unexpected[] = ($route->getName() ?? 'không tên').'  /'.$route->uri();
            }
        }

        sort($unexpected);

        $this->assertSame(
            [],
            $unexpected,
            "Có closure không thuộc loại được phép (chuyển hướng / view tĩnh / hằng):\n".
            implode("\n", $unexpected),
        );
    }

    /**
     * Đo độ dài từng closure trong một file bằng cách đếm ngoặc nhọn.
     *
     * @return list<array{0: int, 1: int}> [dòng bắt đầu, số dòng]
     */
    private function closureLengthsIn(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $found = [];
        $index = 0;

        while ($index < count($lines)) {
            /*
             * Cửa sổ phải dừng ở cuối CÂU LỆNH hiện tại.
             *
             * Bản cũ luôn lấy 3 dòng nên một `Route::get(...)` bình thường đứng
             * ngay trên `Route::middleware(...)->group(function () {` bị coi là có
             * closure, rồi đếm ngoặc nhọn lấn sang cả khối group — báo dài giả.
             * Gặp thật ở finance.php:388 (khối `role:admin` của cấu hình phiếu lương).
             * Route group KHÔNG phải logic nghiệp vụ; đó chính là việc của file route.
             */
            $window = '';

            for ($peek = $index; $peek < min($index + 3, count($lines)); $peek++) {
                $window .= $lines[$peek];

                if (str_ends_with(rtrim($lines[$peek]), ';')) {
                    break; // câu lệnh đã kết thúc, không nhìn sang câu sau
                }
            }

            $startsRoute = preg_match('/Route::(get|post|put|patch|delete|any|match)\(/', $lines[$index]) === 1;
            $hasClosure = preg_match('/function\s*\(|fn\s*\(/', $window) === 1;

            if (! $startsRoute || ! $hasClosure) {
                $index++;

                continue;
            }

            // Arrow function (`fn () => ...`) không có thân trong ngoặc nhọn:
            // nó kết thúc ở dấu `;` của chính câu lệnh. Đếm ngoặc nhọn cho loại
            // này sẽ chạy lấn sang khối phía sau và báo dài giả.
            $isArrowOnly = preg_match('/fn\s*\(/', $window) === 1
                && preg_match('/function\s*\(/', $window) !== 1;

            $depth = 0;
            $opened = false;
            $cursor = $index;

            while ($cursor < count($lines)) {
                if ($isArrowOnly) {
                    if (str_ends_with(rtrim($lines[$cursor]), ';')) {
                        break;
                    }

                    $cursor++;

                    continue;
                }

                $depth += substr_count($lines[$cursor], '{') - substr_count($lines[$cursor], '}');

                if (str_contains($lines[$cursor], '{')) {
                    $opened = true;
                }

                if ($opened && $depth <= 0) {
                    break;
                }

                $cursor++;
            }

            $found[] = [$index + 1, $cursor - $index + 1];
            $index = $cursor + 1;
        }

        return $found;
    }

    /**
     * Đọc mã nguồn thân closure của một route qua Reflection.
     */
    private function sourceOfClosure(\Illuminate\Routing\Route $route): ?string
    {
        $uses = $route->getAction()['uses'] ?? null;

        if (! $uses instanceof \Closure) {
            return null;
        }

        $reflection = new \ReflectionFunction($uses);
        $file = $reflection->getFileName();

        if ($file === false || ! is_file($file)) {
            return null;
        }

        // Route do chính Laravel/gói ngoài đăng ký (vd `/storage/{path}` của
        // FilesystemServiceProvider) không thuộc phạm vi test này.
        if (! str_starts_with($file, base_path('routes'))) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $from = $reflection->getStartLine() - 1;
        $to = $reflection->getEndLine();

        return implode("\n", array_slice($lines, $from, $to - $from));
    }
}
