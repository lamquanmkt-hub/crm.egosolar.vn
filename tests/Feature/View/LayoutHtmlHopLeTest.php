<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chốt lại phần HTML của layout sau đợt rà bằng validator W3C (vnu 26.9.27, chạy cục bộ).
 *
 * Bốn lỗi dưới đây validator ĐÃ báo trên cả 4 trang đo thử; sửa xong lỗi W3C giảm 75 → 54.
 * Mỗi mục ở đây ứng với một lỗi thật, không phải quy ước tự đặt.
 */
final class LayoutHtmlHopLeTest extends TestCase
{
    use DatabaseTransactions;

    private function layout(): string
    {
        return (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
    }

    /** `charset` phải nằm trong 1024 byte đầu; đặt nó SAU một <link> CDN là sai thứ tự. */
    #[Test]
    public function charset_dung_dau_head(): void
    {
        // Bỏ comment Blade trước đã: chữ `<link>` nằm trong lời giải thích cũng bị đếm là thẻ.
        $s = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $this->layout());
        $head = substr($s, strpos($s, '<head>'), 600);

        $viTriCharset = strpos($head, '<meta charset');
        $this->assertNotFalse($viTriCharset, 'layout không còn <meta charset>');

        $truoc = substr($head, 0, $viTriCharset);
        $this->assertSame(0, preg_match_all('/<(link|script|title|meta)\b/', $truoc),
            'có thẻ khác chen trước <meta charset>');
    }

    /** Vùng nội dung là <main> và skip-link trỏ đúng vào nó. */
    #[Test]
    public function co_main_va_skip_link_tro_dung_dich(): void
    {
        $s = $this->layout();

        $this->assertSame(1, substr_count($s, '<main '), 'phải có đúng một <main>');
        $this->assertMatchesRegularExpression('/<main id="([\w-]+)"/', $s);
        preg_match('/<main id="([\w-]+)"/', $s, $m);
        $id = $m[1];

        $this->assertStringContainsString('href="#'.$id.'"', $s, 'skip-link không trỏ vào <main>');
        $this->assertSame(1, substr_count($s, 'class="ego-skip-link"'), 'phải có đúng một skip-link');

        // Tên class/id viết bằng TIẾNG ANH (quy ước repo) — không lẫn tiếng Việt.
        foreach (['noi-dung', 'noidung', 'chinh', 'trang'] as $tuViet) {
            $this->assertStringNotContainsString('id="ego-'.$tuViet, $s, "id lẫn tiếng Việt: {$tuViet}");
        }
    }

    /**
     * `<style>` KHÔNG phải con hợp lệ của `<ul>` — sidebar từng có một khối `@once <style>`
     * lọt vào giữa `<ul class="ego-nav">`.
     *
     * Kiểm trên HTML ĐÃ RENDER chứ không trên nguồn Blade: nguồn có `@if` nên số `<ul>` mở và
     * đóng không cân theo văn bản, đếm kiểu đó chỉ ra kết quả rác.
     */
    #[Test]
    public function khong_co_style_ben_trong_ul(): void
    {
        $nguoiDung = $this->userWithRole('accounting', [
            'name' => 'KT HTML', 'email' => 'layout-html@example.test',
        ], ['page.finance']);

        $html = (string) $this->actingAs($nguoiDung)->get('/finance')->assertOk()->getContent();

        $sau = 0;
        foreach (preg_split('/(<ul\b|<\/ul>|<style\b)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) as $doan) {
            if ($doan === '<ul') {
                $sau++;
            } elseif ($doan === '</ul>') {
                $sau = max(0, $sau - 1);
            } elseif ($doan === '<style' && $sau > 0) {
                $this->fail('có <style> nằm trong <ul> ở trang đã render');
            }
        }

        $this->assertSame(0, $sau, 'HTML render ra có <ul> không đóng');
    }

    /** `aria-label` trên `<div>` trần là ARIA không hợp lệ — phải có role hoặc dùng thẻ ngữ nghĩa. */
    #[Test]
    public function khong_co_aria_label_tren_div_tran(): void
    {
        $viPham = [];

        foreach (['layouts/app', 'layouts/guest', 'partials/navbar', 'partials/sidebar'] as $view) {
            $s = (string) file_get_contents(resource_path("views/{$view}.blade.php"));

            foreach (preg_split('/(?=<div\b)/', $s) as $doan) {
                if (! str_starts_with($doan, '<div')) {
                    continue;
                }
                $the = substr($doan, 0, (int) strpos($doan, '>') + 1);

                if (str_contains($the, 'aria-label') && ! str_contains($the, 'role=')) {
                    $viPham[] = $view.': '.substr(trim($the), 0, 70);
                }
            }
        }

        $this->assertSame([], $viPham, implode("\n", $viPham));
    }
}
