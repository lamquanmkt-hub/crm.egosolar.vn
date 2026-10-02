<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\DTOs\Sales\CommissionRuleRow;
use App\DTOs\Sales\KpiModuleCard;
use App\DTOs\Sales\KpiOpsItem;
use App\DTOs\Sales\KpiTierRow;
use App\DTOs\Sales\SalesSalaryRow;
use App\View\Presenters\Sales\SalesCommissionSettingsPresenter;
use App\View\Presenters\Sales\SalesKpiSettingsPresenter;
use Tests\TestCase;

/**
 * Hai view cấu hình sales sau khi dời 8 khối `@php` sang presenter (2026-09-08): view chỉ in — không
 * `@php`, mọi biến do presenter/controller cấp, thuộc tính DTO là thật. Render thật đã có ở
 * SalesCommissionPagesCharacterizationTest.
 */
final class SalesSettingsPagesTest extends TestCase
{
    private const BLADE_VARIABLES = ['errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component', 'key', 'label', 'idx'];

    public function test_view_kpi_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path('resources/views/sales/kpi/settings.blade.php'));
        $this->assertStringNotContainsString('@php', $source);

        $provided = array_merge(['settings', 'module', 'item'], array_keys((new SalesKpiSettingsPresenter)->viewData([])));
        $this->assertSame([], $this->unprovided($source, $provided), 'biến view dùng mà presenter/controller không cấp');
        $this->assertDtoProperties($source, ['module' => KpiModuleCard::class, 'item' => KpiOpsItem::class]);
    }

    public function test_view_hoa_hong_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path('resources/views/sales/commissions/settings.blade.php'));
        $this->assertStringNotContainsString('@php', $source);

        $engineKeys = ['policy', 'rules', 'products', 'categories', 'brands', 'month', 'salesUsers', 'salarySettings', 'kpiTiers'];
        $provided = array_merge($engineKeys, ['row', 'u'], array_keys((new SalesCommissionSettingsPresenter)->viewData(collect(), collect(), collect(), collect(), (object) [])));
        $this->assertSame([], $this->unprovided($source, $provided), 'biến view dùng mà presenter/engine không cấp');
        $this->assertDtoProperties($source, ['row' => [CommissionRuleRow::class, SalesSalaryRow::class, KpiTierRow::class]]);
    }

    /** @param  list<string>  $provided */
    private function unprovided(string $source, array $provided): array
    {
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);

        return array_values(array_diff(array_unique($m[1]), self::BLADE_VARIABLES, $provided));
    }

    /** @param  array<string, class-string|list<class-string>>  $variables  biến view => DTO (hoặc các DTO cùng dùng tên biến đó) */
    private function assertDtoProperties(string $source, array $variables): void
    {
        foreach ($variables as $variable => $classes) {
            preg_match_all('/\$'.$variable.'->([a-zA-Z]+)/', $source, $m);
            $properties = [];
            foreach ((array) $classes as $class) {
                $properties = array_merge($properties, array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass($class))->getProperties()));
            }
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), "view đọc thuộc tính không có của \${$variable}");
        }
    }
}
