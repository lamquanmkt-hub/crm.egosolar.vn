<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Layout không được kéo lại bootstrap.bundle.min.js.
 *
 * Hành vi Bootstrap JS đã được thay bằng resources/js/bs-compat (dựng ra
 * public/js/bootstrap-compat.js), đối chiếu 4061/4061 thuộc tính DOM qua 14 bước
 * thao tác với Bootstrap 5.3.3 thật. Nạp lại bản CDN sẽ có hai bộ xử lý cùng
 * nghe data-bs-* — mỗi cú nhấp chạy hai lần, modal mở rồi đóng ngay.
 */
final class BootstrapJsRemovedTest extends TestCase
{
    private const LAYOUT = 'resources/views/layouts/app.blade.php';

    #[Test]
    public function khong_view_nao_con_nap_bootstrap_js(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            $noiDung = $this->doc($duongDan);

            foreach (explode("\n", $noiDung) as $i => $dong) {
                if (str_contains($dong, '{{--') || str_contains($dong, '<!--')) {
                    continue; // dòng chú thích nhắc tên tệp cũ thì không tính
                }

                if (preg_match('/<script[^>]+bootstrap(\.bundle)?(\.min)?\.js/', $dong)) {
                    $viPham[] = $this->rel($duongDan).':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $viPham, "Còn view nạp Bootstrap JS:\n".implode("\n", $viPham));
    }

    #[Test]
    public function layout_nap_ban_thay_the_truoc_stack_scripts(): void
    {
        // Bỏ chú thích Blade trước khi dò vị trí: chính chú thích giải thích thứ tự
        // nạp cũng nhắc tên @stack('scripts'), dò thẳng sẽ trúng nhầm vào đó.
        $noiDung = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $this->doc(base_path(self::LAYOUT)));

        $viTriCompat = strpos($noiDung, "asset('js/bootstrap-compat.js')");
        $viTriStack = strpos($noiDung, "@stack('scripts')");

        $this->assertNotFalse($viTriCompat, 'Layout không nạp js/bootstrap-compat.js.');
        $this->assertNotFalse($viTriStack, "Layout không còn @stack('scripts').");

        $this->assertLessThan(
            $viTriStack,
            $viTriCompat,
            "bootstrap-compat.js phải nạp TRƯỚC @stack('scripts'): mã nội tuyến trong view "
            .'gọi new bootstrap.Modal(...) ngay lúc phân tích trang.'
        );
    }

    #[Test]
    public function ban_dung_da_duoc_commit(): void
    {
        $this->assertFileExists(
            public_path('js/bootstrap-compat.js'),
            'Thiếu public/js/bootstrap-compat.js — chạy `pnpm run build` rồi commit.'
        );

        $this->assertStringNotContainsString(
            'type="module"',
            $this->doc(base_path(self::LAYOUT)),
            'bootstrap-compat.js phải là script cổ điển, không được là module.'
        );
    }

    #[Test]
    public function moi_co_che_bootstrap_dang_dung_deu_co_ban_thay_the(): void
    {
        $canCo = [
            'modal' => 'modal.js',
            'offcanvas' => 'offcanvas.js',
            'collapse' => 'collapse.js',
            'tab' => 'tab.js',
            'alert' => 'alert.js',
            'dropdown' => 'dropdown.js',
            'toast' => 'toast.js',
        ];

        $dangDung = [];

        foreach ($this->dsBlade() as $duongDan) {
            preg_match_all('/data-bs-(?:toggle|dismiss)="([a-z]+)"/', $this->doc($duongDan), $m);
            foreach ($m[1] as $coChe) {
                $dangDung[$coChe === 'pill' || $coChe === 'list' ? 'tab' : $coChe] = true;
            }
        }

        foreach (array_keys($dangDung) as $coChe) {
            $this->assertArrayHasKey(
                $coChe,
                $canCo,
                "View dùng data-bs-*=\"$coChe\" nhưng bs-compat chưa có bản thay thế cho cơ chế này."
            );

            $this->assertFileExists(base_path('resources/js/bs-compat/'.$canCo[$coChe]));
        }
    }

    private function doc(string $duongDan): string
    {
        return (string) file_get_contents($duongDan);
    }

    private function rel(string $duongDan): string
    {
        return str_replace(base_path().'/', '', $duongDan);
    }
}
