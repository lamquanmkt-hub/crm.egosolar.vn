<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\View\Components\Ui\Input;
use Tests\TestCase;

/**
 * `<x-ui.input type="file">` phải giữ nguyên nút "Chọn tệp" kiểu Bootstrap.
 *
 * ## Vì sao có test này
 * Nút đó do TRÌNH DUYỆT vẽ (`::file-selector-button`), Bootstrap tô lại bằng một
 * luật riêng. Bản đầu của component không có phần này, nên ô tệp đã chuyển hiển
 * thị bằng nút mặc định của trình duyệt.
 *
 * Đo bằng CDP ngày 2026-09-04 giữa `<input class="form-control" type="file">` và
 * component khi CHƯA có luật: **lệch 25 thuộc tính** — nút cao 31,5px -> 25,5px,
 * nền `#f8f9fa` -> `#efefef`, viền `solid` -> `outset`, chữ `#212529` -> đen,
 * và ô cao 33,5px -> 39,5px. Sau khi thêm luật: 48/48 giống hệt.
 *
 * Sai sót này đã lọt vào production ở `marketing/reports/content_calendar`
 * (2 ô tệp) vì phép đo lúc chuyển trang đó không soi pseudo-element.
 *
 * ## Hai lưới, vì hỏng theo hai kiểu khác nhau
 * 1. Component phải SINH ra các lớp `tw:file:*`.
 * 2. Bản CSS đã build phải THẬT SỰ chứa luật `::file-selector-button` — Tailwind
 *    có tiền lệ cắt mất thứ nó tưởng không ai dùng (xem {@see UiCssVariablesTest}).
 */
final class UiFileInputTest extends TestCase
{
    public function test_component_sinh_lop_cho_nut_chon_tep(): void
    {
        $classes = $this->classesFor('file');

        foreach ([
            'tw:file:px-3',
            'tw:file:py-[6px]',
            'tw:file:bg-[#f8f9fa]',
            'tw:file:text-[#212529]',
            'tw:file:border-inherit',
            'tw:file:border-solid',
            'tw:file:rounded-none',
            'tw:file:pointer-events-none',
            'tw:[&[type=file]]:overflow-hidden',
        ] as $needle) {
            $this->assertStringContainsString($needle, $classes,
                "thiếu lớp `$needle` — nút chọn tệp sẽ về mặc định trình duyệt");
        }
    }

    /** Ô KHÔNG phải file thì không được dính mớ lớp đó cho rác. */
    public function test_o_thuong_khong_kem_lop_cua_o_tep(): void
    {
        $this->assertStringNotContainsString('tw:file:', $this->classesFor('text'));
        $this->assertStringNotContainsString('tw:file:', $this->classesFor(null));
    }

    /**
     * Viền nút phải là `inherit`: luật riêng của trang đổi màu viền ô (proposals
     * dùng #dbe3ee) và nút phải đổi theo. Ghi cứng màu là sai ngay trang kế tiếp.
     */
    public function test_vien_nut_ke_thua_mau_cua_o(): void
    {
        $this->assertStringContainsString('tw:file:border-inherit', $this->classesFor('file'));
        $this->assertStringNotContainsString('tw:file:border-[#', $this->classesFor('file'));
    }

    public function test_css_da_build_co_luat_cho_nut_chon_tep(): void
    {
        $files = glob(public_path('build/assets/app-*.css')) ?: [];

        if ($files === []) {
            $this->markTestSkipped('chưa có bản build — chạy `npm run build`');
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $css = (string) file_get_contents($files[0]);

        $this->assertStringContainsString('file-selector-button', $css,
            "CSS đã build không có luật `::file-selector-button`.\n".
            'Chạy `npm run build`; nếu vẫn thiếu thì Tailwind đã cắt mất biến thể `file:`.');
    }

    private function classesFor(?string $type): string
    {
        $input = new Input;
        $input->withAttributes($type === null ? [] : ['type' => $type]);

        return $input->classes();
    }
}
