<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * View nào nằm trong một "cụm CSS" thì MỌI partial nó kéo theo cũng phải sạch.
 *
 * ## Vì sao có test này — một hồi quy thật do chính đợt chuyển gây ra
 * Ngày 2026-09-04, khi chuyển cụm kho, bộ chọn `.form-control`/`.form-select`
 * bị bỏ khỏi `public/css/ego-inventory-enterprise.css`. Tôi có kiểm "các view
 * còn lại của cụm không có ô Bootstrap" nhưng kiểm SÓT `warehouses/_form` —
 * partial mà `warehouses/create` và `warehouses/edit` include.
 *
 * Hậu quả đo được trên hai trang đó: 2 ô nhập mất định dạng — cao 42px -> 38px,
 * `min-height` 42px -> 0, đệm 8/11px -> 6/12px, cỡ chữ 12,5px -> 16px, đậm 650 ->
 * 400, màu chữ #122033 -> #212529, viền #cfdae4 -> #dee2e6. Đã deploy rồi mới
 * phát hiện.
 *
 * Bài học: kiểm partial phải đi ĐỆ QUY từ view gốc, không chỉ nhìn một lớp.
 */
final class ClusterPartialsConvertedTest extends TestCase
{
    /** @return array<string, array{0: list<string>}> cụm => các view gốc */
    public static function clusters(): array
    {
        return [
            'cụm kho (ego-inventory-enterprise.css)' => [[
                'warehouses/index', 'warehouses/create', 'warehouses/edit', 'warehouses/inventory',
                'products/create', 'products/edit', 'products/index_input', 'products/index_output',
                'products/goods-receipts/index',
            ]],
            'cụm đề nghị thanh toán (ego-payment-requests-enterprise.css)' => [[
                'payment_requests/index', 'payment_requests/show',
                'advance_requests/index', 'advance_requests/show',
                'settlement_requests/index', 'settlement_requests/show',
            ]],
        ];
    }

    /**
     * @param  list<string>  $roots
     */
    #[DataProvider('clusters')]
    public function test_moi_partial_trong_cum_deu_da_chuyen(array $roots): void
    {
        $dirty = [];

        foreach ($roots as $root) {
            foreach ($this->includesOf($root, []) as $view) {
                $path = resource_path('views/'.$view.'.blade.php');

                if (! is_file($path)) {
                    continue;
                }

                $count = $this->bootstrapClasses((string) file_get_contents($path));

                if ($count > 0) {
                    $dirty[] = sprintf('%s (%d ô, kéo theo từ %s)', $view, $count, $root);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($dirty)),
            "Partial trong cụm còn lớp Bootstrap, nhưng CSS của cụm đã bỏ bộ chọn đó\n".
            "nên chúng KHÔNG còn được tô kiểu:\n".implode("\n", array_unique($dirty)));
    }

    /**
     * @param  list<string>  $seen
     * @return list<string>
     */
    private function includesOf(string $view, array $seen): array
    {
        $path = resource_path('views/'.$view.'.blade.php');

        if (! is_file($path) || in_array($view, $seen, true)) {
            return [];
        }

        $seen[] = $view;
        preg_match_all("/@include(?:If|When)?\(\s*'([^']+)'/", (string) file_get_contents($path), $m);

        $found = [];

        foreach ($m[1] as $include) {
            $child = str_replace('.', '/', $include);
            $found[] = $child;
            $found = array_merge($found, $this->includesOf($child, $seen));
        }

        return $found;
    }

    private function bootstrapClasses(string $source): int
    {
        $blade = (string) preg_replace(
            '/<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|\{\{--.*?--\}\}/s',
            '', $source);

        preg_match_all('/class="[^"]*(?<![\w-])form-(?:control|select|label)(?![\w-])[^"]*"/', $blade, $m);

        return count($m[0]);
    }
}
