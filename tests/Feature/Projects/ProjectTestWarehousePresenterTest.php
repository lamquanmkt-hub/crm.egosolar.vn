<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\DTOs\Projects\WarehouseItemRow;
use App\View\Presenters\Projects\ProjectTestWarehousePresenter;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * ProjectTestWarehousePresenter thay 3 khối `@php` của trang xuất kho công trình bản thử
 * (2026-09-25): một khối suy ba cờ trạng thái, và HAI khối một dòng giống hệt nhau nằm trong hai
 * vòng lặp khác nhau trên cùng danh sách.
 */
final class ProjectTestWarehousePresenterTest extends TestCase
{
    private const VIEW = 'resources/views/project-test/warehouse-show-v2.blade.php';

    private function item(array $allocations): object
    {
        return (object) ['allocations' => new Collection($allocations)];
    }

    private function request(string $status, ?string $warehouseStatus, array $items, string $state = 'waiting_match'): object
    {
        return (object) [
            'status' => $status,
            'warehouse_status' => $warehouseStatus,
            'computed_warehouse_state' => $state,
            'items' => new Collection($items),
        ];
    }

    public function test_view_khong_con_php_va_chi_doc_thuoc_tinh_that_cua_dto(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        // Hai biến của khối cũ không được còn dùng trực tiếp nữa.
        $this->assertDoesNotMatchRegularExpression('/(?<!row->)\$item(?![A-Za-z0-9_])/', $source);
        $this->assertDoesNotMatchRegularExpression('/(?<!row->)\$allocation/', $source);

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(WarehouseItemRow::class))->getProperties()
        );
        $this->assertNotSame([], $m[1]);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)));
    }

    public function test_ba_co_trang_thai(): void
    {
        $svc = new ProjectTestWarehousePresenter;

        $approved = $svc->viewData($this->request('approved', null, [], 'waiting_match'));
        $this->assertSame('waiting_match', $approved['state']);
        $this->assertFalse($approved['locked']);
        $this->assertFalse($approved['reserved']);

        $reserved = $svc->viewData($this->request('preparing', 'reserved', [], 'reserved'));
        $this->assertTrue($reserved['reserved']);
        $this->assertFalse($reserved['locked'], 'giữ hàng chưa phải là đã xuất');

        $issued = $svc->viewData($this->request('issued', 'issued', [], 'issued'));
        $this->assertTrue($issued['locked']);
        $this->assertFalse($issued['reserved'], 'warehouse_status = issued nên KHÔNG phải reserved');
    }

    public function test_ghep_dong_vat_tu_voi_ban_ghep_kho_dau_tien(): void
    {
        $alloc1 = (object) ['id' => 11, 'status' => 'matched'];
        $alloc2 = (object) ['id' => 12, 'status' => 'matched'];

        $data = (new ProjectTestWarehousePresenter)->viewData($this->request('approved', null, [
            $this->item([$alloc1, $alloc2]),   // lấy bản ĐẦU TIÊN
            $this->item([]),                   // chưa ghép kho -> null
        ]));

        $this->assertCount(2, $data['itemRows']);
        $this->assertSame($alloc1, $data['itemRows'][0]->allocation, 'phải là bản ghép đầu tiên');
        $this->assertNull($data['itemRows'][1]->allocation, 'chưa ghép kho thì null — view dùng $allocation?->');
    }

    public function test_khong_co_dong_vat_tu(): void
    {
        $data = (new ProjectTestWarehousePresenter)->viewData($this->request('approved', null, []));

        $this->assertSame([], $data['itemRows']);
    }
}
