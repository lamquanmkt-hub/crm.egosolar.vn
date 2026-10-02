<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Contracts\Services\WarehouseServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Models\Core\Company;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Stock\ProductStock as CrmProductStock;
use App\Support\EgoCompanyContext;
use App\Support\SchemaCache;

/**
 * Controller quản lý kho hàng (Warehouse) — chỉ điều phối, nghiệp vụ nằm ở service.
 */
class WarehouseController extends Controller
{
    private function vnWarehouseQuery()
    {
        return Warehouse::query()->where(function ($query) {
            $hasCondition = false;

            if (SchemaCache::hasColumn('crm_warehouses', 'company_id')) {
                $query->where('company_id', EgoCompanyContext::defaultCompanyId());
                $hasCondition = true;
            }

            if (SchemaCache::hasTable('company_warehouse')) {
                $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                $query->{$method}('companies', fn ($companyQuery) => $companyQuery->where('companies.id', EgoCompanyContext::defaultCompanyId()));
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function ensureVnWarehouse(Warehouse $warehouse): void
    {
        abort_unless($this->vnWarehouseQuery()->whereKey($warehouse->getKey())->exists(), 404);
    }

    public function __construct(protected WarehouseServiceInterface $service)
    {
        $this->authorizeResource(Warehouse::class, 'warehouse');
    }

    public function index()
    {
        $companies = Company::query()->whereKey(EgoCompanyContext::defaultCompanyId())->get();
        $selectedCompanyIds = [EgoCompanyContext::defaultCompanyId()];

        $warehouses = $this->vnWarehouseQuery()
            ->with(['companies' => fn ($query) => $query->where('companies.id', EgoCompanyContext::defaultCompanyId()), 'manager'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses', 'companies', 'selectedCompanyIds'));
    }

    public function create()
    {
        $companies = Company::query()->whereKey(EgoCompanyContext::defaultCompanyId())->get();

        return view('warehouses.create', compact('companies'));
    }

    public function store(WarehouseRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = EgoCompanyContext::defaultCompanyId();
        $companyIds = [EgoCompanyContext::defaultCompanyId()];

        $warehouse = $this->service->create($data);

        // ✅ sync pivot many-to-many
        $warehouse->companies()->sync($companyIds);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Tạo kho thành công');
    }

    public function edit(Warehouse $warehouse)
    {
        $this->ensureVnWarehouse($warehouse);

        $companies = Company::query()->whereKey(EgoCompanyContext::defaultCompanyId())->get();
        $warehouse->load('companies');

        return view('warehouses.edit', compact('warehouse', 'companies'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $this->ensureVnWarehouse($warehouse);

        $data = $request->validated();
        $data['company_id'] = EgoCompanyContext::defaultCompanyId();
        $companyIds = [EgoCompanyContext::defaultCompanyId()];

        $this->service->update($warehouse, $data);

        // ✅ sync pivot many-to-many
        $warehouse->companies()->sync($companyIds);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Cập nhật kho thành công');
    }

    public function destroy(Warehouse $warehouse)
    {
        $this->ensureVnWarehouse($warehouse);

        $this->service->delete($warehouse);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Xóa kho thành công');
    }

    public function inventory(Warehouse $warehouse)
    {
        $this->ensureVnWarehouse($warehouse);

        $inventory = CrmProductStock::query()
            ->with('product')
            ->where('warehouse_id', $warehouse->id)
            ->where('company_id', EgoCompanyContext::defaultCompanyId())
            ->get();

        return view('warehouses.inventory', compact('warehouse', 'inventory'));
    }
}
