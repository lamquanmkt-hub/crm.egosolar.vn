<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Mọi view phải biên dịch ra PHP hợp lệ.
 *
 * ## Vì sao `view:cache` không đủ
 * `php artisan view:cache` chỉ BIÊN DỊCH, không kiểm PHP sinh ra có chạy được
 * không. Một tệp hỏng vẫn "cached successfully" rồi mới nổ lúc người dùng mở
 * trang: `syntax error, unexpected end of file, expecting endif`.
 *
 * ## Hai cái bẫy đã vấp thật, cùng một gốc
 * Blade quét NGUYÊN tệp, kể cả bên trong `<style>` và bình luận CSS:
 *
 * 1. Viết tên thẻ component kèm dấu ngoặc góc vào bình luận CSS -> Blade coi là
 *    thẻ thật chưa đóng và nuốt hết phần còn lại của tệp (2026-09-04,
 *    content_calendar_show).
 * 2. Viết a-còng kèm tên chỉ thị vào bình luận CSS -> Blade coi là chỉ thị thật.
 *
 * Cả hai đều không làm `view:cache` báo lỗi.
 *
 * ## Bẫy thứ ba: thẻ component KHÔNG được biên dịch
 * Blade bỏ qua thẻ `x-...` nào có `{{ }}` trần hoặc chỉ thị `@...` trong vùng
 * thuộc tính — nó để NGUYÊN chuỗi `<x-ui.input …>` ra HTML. Trang vẫn 200, PHP
 * vẫn hợp lệ, chỉ là mất hẳn ô nhập. Đã vấp ở finance/accounts/create với
 * `{{ isset($account) ? 'disabled' : '' }}` (và `@disabled(...)` cũng vậy).
 * Cách đúng là thuộc tính ràng buộc: `:disabled="isset($account)"`.
 */
final class BladeCompilesTest extends TestCase
{
    /**
     * Kiểm cú pháp NGAY TRONG tiến trình bằng `token_get_all(…, TOKEN_PARSE)`: cờ này bắt
     * trình phân tích chạy thật và ném ParseError y như `php -l` với lỗi ngữ pháp
     * (`unexpected end of file, expecting endif`, ngoặc chưa đóng…). Trước đây mỗi view
     * là một tiến trình `php -l` + tệp tạm: 306 view mất 7,6 s; nay dưới 1 s.
     * Không bắt lỗi thời-điểm-biên-dịch ngoài ngữ pháp (khai báo trùng hàm…) — view
     * Blade không sinh ra loại đó.
     */
    public function test_moi_view_bien_dich_ra_php_hop_le(): void
    {
        $broken = [];
        foreach ($this->views() as $view) {
            $name = str_replace(resource_path('views/'), '', $view);
            $compiled = Blade::compileString((string) file_get_contents($view));
            // Thẻ component còn nguyên trong bản biên dịch = Blade đã bỏ qua nó.
            //
            // Bỏ khối <style> trước khi soi: bình luận CSS trong dự án này có nhắc
            // tên thẻ (ví dụ `<x-ui.*>`) và từng làm test báo nhầm. Tên thẻ cũng
            // phải kết thúc bằng ký tự chữ rồi tới khoảng trắng / `/` / `>`, để
            // không dính mấy chuỗi mô tả như `<x-ui.*`.
            $withoutStyle = (string) preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $compiled);
            if (preg_match('/<x-[a-z][\w.:-]*\w(?=[\s\/>])/i', $withoutStyle, $tag)) {
                $broken[] = $name.': thẻ '.$tag[0].' KHÔNG được biên dịch — nhiều khả năng trong thẻ có '.
                    '`{{ }}` trần hoặc chỉ thị `@...`; dùng thuộc tính ràng buộc `:attr="..."`.';
            }
            try {
                token_get_all($compiled, TOKEN_PARSE);
            } catch (\ParseError $e) {
                $broken[] = sprintf('%s: Parse error: %s in (bản biên dịch) on line %d', $name, $e->getMessage(), $e->getLine());
            }
        }
        $this->assertSame([], $broken, "View biên dịch ra PHP KHÔNG hợp lệ:\n".implode("\n", $broken));
    }

    /** @return list<string> */
    private function views(): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }
}
