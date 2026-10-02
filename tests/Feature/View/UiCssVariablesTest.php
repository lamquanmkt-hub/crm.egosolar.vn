<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use Tests\TestCase;

/**
 * Mọi biến `--ui-*` mà component gọi phải THẬT SỰ có mặt trong CSS đã build.
 *
 * ## Vì sao cần lưới này
 * Tailwind v4 loại bỏ biến khai trong `@theme` mà nó cho là không ai dùng, và nó
 * KHÔNG nhận ra `var(--…)` nằm trong giá trị tuỳ ý của class
 * (`tw:[background-image:var(--ui-select-caret)]`).
 *
 * Hậu quả đã gặp thật (2026-09-04): luật lớp vẫn sinh ra đầy đủ, nên đọc mã nguồn
 * và đọc CSS đều thấy "có", nhưng khai báo biến bị cắt nên `var()` rỗng và ảnh nền
 * thành `none`. Biểu tượng cảnh báo của `x-ui.input` hỏng suốt từ lúc viết mà
 * không ai thấy — vì component chưa được áp vào view nào.
 *
 * Chỉ phép đo mới phát hiện được. Test này là bản rẻ tiền của phép đo đó: giữ cho
 * biến luôn nằm ở `:root` chứ không rơi lại vào `@theme`.
 */
final class UiCssVariablesTest extends TestCase
{
    public function test_moi_bien_ui_duoc_goi_deu_co_khai_bao_trong_css_da_build(): void
    {
        $css = $this->builtCss();
        $used = $this->variablesUsedByComponents();

        $this->assertNotEmpty($used, 'không tìm thấy biến --ui-* nào trong component; test này mất tác dụng');

        $missing = [];

        foreach ($used as $variable => $where) {
            // `assertStringContainsString` sẽ đổ nguyên 20 KB CSS ra màn hình khi
            // hỏng, nên tự kiểm rồi báo gọn.
            if (! str_contains($css, $variable.':')) {
                $missing[] = "$variable (gọi ở $where)";
            }
        }

        $this->assertSame([], $missing, "Biến --ui-* được gọi nhưng KHÔNG có khai báo trong CSS đã build:\n".
            implode("\n", $missing)."\n\n".
            "Nhiều khả năng chúng đang nằm trong `@theme` và bị Tailwind cắt — nó không nhận ra\n".
            "`var(--…)` viết trong giá trị tuỳ ý của class. Chuyển sang `:root` trong\n".
            'resources/css/app.css rồi chạy `npm run build`.');
    }

    private function builtCss(): string
    {
        $files = glob(public_path('build/assets/app-*.css')) ?: [];

        if ($files === []) {
            $this->markTestSkipped('chưa có bản build — chạy `npm run build`');
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return (string) file_get_contents($files[0]);
    }

    /** @return array<string, string> tên biến => nơi gọi */
    private function variablesUsedByComponents(): array
    {
        $found = [];
        $dir = app_path('View/Components');

        if (! is_dir($dir)) {
            return $found;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/var\((--ui-[a-z0-9-]+)\)/i', (string) file_get_contents($file->getPathname()), $m);

            foreach ($m[1] as $variable) {
                $found[$variable] = $file->getBasename();
            }
        }

        return $found;
    }
}
