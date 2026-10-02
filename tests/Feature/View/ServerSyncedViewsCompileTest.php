<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Bắt thuộc tính HTML bị chèn vào biểu thức Blade khi đồng bộ bản sửa trên server. */
final class ServerSyncedViewsCompileTest extends TestCase
{
    /** PHP lint trực tiếp Blade không thấy lỗi trong biểu thức, phải kiểm mã đã biên dịch. */
    #[DataProvider('views')]
    public function test_php_sau_khi_bien_dich_blade_hop_le(string $view): void
    {
        $compiled = app('blade.compiler')->compileString((string) file_get_contents(resource_path('views/'.$view.'.blade.php')));
        $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
    }

    /** @return array<string, array{string}> */
    public static function views(): array
    {
        return [
            'KPI kỹ thuật' => ['kythuat/kpis'],
            'Nhập kho' => ['products/goods-receipts/index'],
            'Báo giá kinh doanh' => ['sales_quotations/form'],
            'Báo giá công trình' => ['sites/quote'],
        ];
    }
}
