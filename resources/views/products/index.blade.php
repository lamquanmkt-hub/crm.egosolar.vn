@extends('layouts.app')

@section('content')
<div class="container-fluid tw:px-4 tw:mt-4 ego-products-page">

  {{-- HEADER --}}
  <div class="tw:flex flex-wrap tw:justify-between tw:items-end tw:gap-2 mb-3">
    <div>
      <div class="tw:flex tw:items-center tw:gap-2">
        <div class="ego-page-dot"></div>
        <h3 class="tw:font-bold tw:mb-0">Danh sách sản phẩm</h3>
      </div>
      <div class="tw:text-[rgba(33,37,41,0.75)] small">Quản lý sản phẩm / tồn kho / giá</div>
    </div>

    <div class="tw:flex flex-wrap tw:gap-2">
      @if(\Illuminate\Support\Facades\Route::has('products.export'))
        <x-ui.button variant="none" size="none" class="ego-btn-soft" href="{{ route('products.export', request()->query()) }}">
          <i class="bi bi-file-earmark-excel"></i> Tải Excel
        </x-ui.button>
      @endif

      @if($canManageProducts)
        <x-ui.button variant="none" size="none" class="ego-btn-primary" href="{{ route('products.create', request()->query()) }}">
          <i class="bi bi-plus-circle"></i> Thêm sản phẩm
        </x-ui.button>
      @endif
    </div>
  </div>

  {{-- MESSAGE --}}
  @if(session('success'))
    <x-ui.alert variant="success" :dismissible="true" class="tw:py-2">
      <i class="bi bi-check2-circle me-1"></i>
      {{ session('success') }}
    </x-ui.alert>
  @endif

  <div class="tw:row tw:g-3 mb-3">
    <div class="tw:col12-12">
      <div class="ego-company-tab active" style="cursor:default">
        <div class="icon-box"><i class="bi bi-building-check"></i></div>
        <div class="info flex-grow-1">
          <div class="title">CÔNG TY TNHH EGO VIỆT NAM</div>
          <div class="desc">Kho và sản phẩm được quản lý tập trung tại EGO Việt Nam</div>
        </div>
        <div class="check-mark"><i class="bi bi-check-circle-fill"></i></div>
      </div>
    </div>
  </div>

  {{-- FILTER FORM --}}
  <form method="GET" id="filterForm" class="mb-3">
    <input type="hidden" name="company_id" id="company_id_input" value="1">
    <input type="hidden" name="price_tier_id" id="price_tier_id" value="{{ $selectedPriceTier }}">

    <x-ui.card class="ego-card">
      <x-ui.card-body class="ego-card-body">
        <div class="tw:row tw:g-2 tw:items-end">

          <div class="tw:col12-12 tw:min-[62rem]:col12-3">
            <x-ui.label class="ego-label">Tìm kiếm</x-ui.label>
            <div class="input-group ego-inputgroup">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <x-ui.input type="text" name="search" class="pi-input"
                     placeholder="Tên sản phẩm, SKU..."
                     value="{{ request('search') }}" />
            </div>
          </div>

          <div class="tw:col12-12 tw:min-[62rem]:col12-3">
            <x-ui.label class="ego-label">Kho</x-ui.label>
            <x-ui.select name="warehouse_id" class="pi-input ego-select">
              <option value="">Tất cả kho</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}" {{ (string)$selectedWarehouse === (string)$w->id ? 'selected' : '' }}>
                  {{ $w->name }}
                </option>
              @endforeach
            </x-ui.select>
          </div>

          <div class="tw:col12-12 tw:min-[62rem]:col12-3">
            <x-ui.label class="ego-label">Danh mục</x-ui.label>
            <x-ui.select name="category_id" class="pi-input ego-select">
              <option value="">Tất cả danh mục</option>
              @foreach($categories as $c)
                <option value="{{ $c->id }}" {{ (string)$selectedCategory === (string)$c->id ? 'selected' : '' }}>
                  {{ str_repeat('— ', (int) ($c->level ?? 0)) }}{{ $c->name }}
                </option>
              @endforeach
            </x-ui.select>
          </div>

          <div class="tw:col12-12 tw:min-[62rem]:col12-3">
            <x-ui.label class="ego-label">Thương hiệu</x-ui.label>
            <x-ui.select name="brand_id" class="pi-input ego-select">
              <option value="">Tất cả thương hiệu</option>
              @foreach($brands as $b)
                <option value="{{ $b->id }}" {{ (string)$selectedBrand === (string)$b->id ? 'selected' : '' }}>
                  {{ $b->name }}
                </option>
              @endforeach
            </x-ui.select>
          </div>

          {{-- ✅ ĐÃ XOÁ: Sắp xếp theo + Chiều --}}

          <div class="tw:col12-12 tw:flex flex-wrap tw:gap-2 tw:mt-2">
            <x-ui.button variant="none" size="none" class="ego-btn-primary" type="submit">
              <i class="bi bi-funnel"></i> Lọc
            </x-ui.button>
            <x-ui.button variant="none" size="none" class="ego-btn-soft" href="{{ route('products.index', ['company_id' => $currentCompanyId]) }}">
              <i class="bi bi-arrow-counterclockwise"></i> Reset
            </x-ui.button>
          </div>

        </div>
      </x-ui.card-body>
    </x-ui.card>
  </form>

  {{-- TABLE --}}
  <x-ui.card class="ego-card">
    <div class="table-responsive ego-table-wrap">
      <table class="table align-middle tw:mb-0 ego-products-table">
        <thead>
        <tr class="tw:text-center">
          <th style="width: 78px;">STT</th>
          <th class="tw:text-left" style="min-width: 320px;">Tên</th>
          <th class="tw:text-left" style="width: 170px;">SKU</th>
          <th class="tw:text-left" style="min-width: 280px;">Ghi chú</th>

          @if($canViewCost)
            <th class="tw:text-right" style="width: 160px;">Giá vốn</th>
          @endif

          <th style="width: 190px;">
            <div class="ego-pricehead">
              <x-ui.select size="sm" class="pi-input ego-tier-select"
                      onchange="
                        document.getElementById('price_tier_id').value=this.value;
                        document.getElementById('filterForm').submit();
                      ">
                <option value="">Giá mặc định</option>
                @foreach($priceTiers as $t)
                  <option value="{{ $t->id }}" {{ (string)$selectedPriceTier === (string)$t->id ? 'selected' : '' }}>
                    {{ $t->name }}
                  </option>
                @endforeach
              </x-ui.select>
            </div>
          </th>

          <th style="width: 120px;">Số lượng</th>
          <th style="width: 170px;">Danh mục</th>
          <th style="width: 170px;">Thương hiệu</th>
          <th style="width: 110px;">Hình ảnh</th>

          @if($canManageProducts)
            <th style="width: 190px;">Hành động</th>
          @endif
        </tr>
        </thead>

        <tbody>
        @forelse($rows as $row)

          <tr>
            <td class="tw:text-center">
              <span class="ego-stt">
                {{ ($products->currentPage() - 1) * $products->perPage() + $loop->iteration }}
              </span>
            </td>

            <td class="tw:text-left">
              <div class="tw:font-semibold ego-name">{{ $row->product->name }}</div>
              @if($row->lotTitle)
                <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-1">
                  <i class="bi bi-box-seam me-1"></i>{{ $row->lotTitle }}
                  @if(!empty($row->product->warehouse_name)) · {{ $row->product->warehouse_name }} @endif
                </div>
              @endif
            </td>

            <td class="tw:text-left">
              @if(!empty($row->product->sku))
                <span class="ego-sku">{{ $row->product->sku }}</span>
              @else
                <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
              @endif
            </td>

            <td class="tw:text-left">
              <div class="ego-note {{ $row->note ? '' : 'text-muted' }}" title="{{ $row->note ?? '' }}">
                {{ $row->note ?: '—' }}
              </div>
            </td>

            @if($canViewCost)
              <td class="tw:text-right">
                <span class="ego-money tw:text-[#212529]">
                  {{ number_format($row->costBefore) }}
                </span>
              </td>
            @endif

<td class="tw:text-right">
  <span class="ego-money tw:text-[#198754]">
    {{ number_format($row->sellAfter) }}
  </span>
  <div class="small tw:text-[rgba(33,37,41,0.75)]">
    VAT {{ rtrim(rtrim(number_format($row->vatPercent, 2), '0'), '.') }}%
  </div>

            <td class="tw:text-center">
              <span class="ego-qty {{ $row->displayQty <= 0 ? 'is-zero' : '' }}">
                {{ number_format($row->displayQty) }}
              </span>
            </td>

            <td class="tw:text-center">
              @if($row->product->category)
                <span class="ego-badge">{{ $row->product->category->name }}</span>
              @else
                <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
              @endif
            </td>

            <td class="tw:text-center">
              @if($row->product->brand)
                <span class="ego-badge ego-badge-brand">{{ $row->product->brand->name }}</span>
              @else
                <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
              @endif
            </td>

            <td class="tw:text-center">
              @if($row->imageUrl)
                <a href="{{ $row->imageUrl }}" target="_blank" class="ego-thumb" title="Mở ảnh">
                  <img src="{{ $row->imageUrl }}"
                       alt="{{ $row->product->name }}"
                       width="42" height="42"
                       class="rounded-3 border object-fit-cover">
                </a>
              @else
                <span class="tw:text-[rgba(33,37,41,0.75)] small">Không có</span>
              @endif
            </td>

            @if($canManageProducts)
              <td class="tw:text-center">
                <div class="tw:inline-flex tw:gap-2">
                  @if(!empty($row->product->id))
                    <x-ui.button variant="none" size="sm" class="ego-btn-warn" href="{{ route('products.edit', ['product' => $row->product->id] + request()->query()) }}">
                      <i class="bi bi-pencil-square"></i> Sửa
                    </x-ui.button>
                  @endif

                  @if(!empty($row->product->id))
                    <form action="{{ route('products.destroy', $row->product->id) }}" method="POST" class="d-inline">
                      @csrf
                      @method('DELETE')
                      <x-ui.button variant="none" size="sm" type="submit" class="ego-btn-danger" onclick="return confirm('Bạn chắc chắn muốn xóa?')">
                        <i class="bi bi-trash"></i> Xóa
                      </x-ui.button>
                    </form>
                  @endif
                </div>
              </td>
            @endif
          </tr>
        @empty
          <tr>
            <td colspan="{{ $colspan }}" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">
              Không có dữ liệu
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <x-ui.card-body class="tw:flex tw:justify-end tw:py-2">
      @if($products && method_exists($products, 'links'))
        {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
      @endif
    </x-ui.card-body>
  </x-ui.card>

</div>

<script>
  function selectCompany(id) {
    // ✅ Khi đổi công ty: reset các filter phụ để tránh “đang chọn kho cty A” nhưng chuyển sang cty B
    const url = new URL(window.location.href);
    url.searchParams.set('company_id', id);
    url.searchParams.delete('warehouse_id');
    url.searchParams.delete('category_id');
    url.searchParams.delete('brand_id');
    window.location.href = url.toString();
  }
</script>

<style> /* giữ nguyên CSS của bạn */ .ego-products-page{
    --ego:#0E7C86;
    --ego2:#0B5E66;
    --border: rgba(12, 92, 100, .12);
    --muted:#64748b;
  }.ego-company-tab{ background:#fff;border:1px solid var(--border);border-radius:16px;padding:16px 20px;cursor:pointer;display:flex;align-items:center;gap:16px;position:relative;transition:all .2s ease;box-shadow:0 4px 12px rgba(15,23,42,.03);height:100%; }.ego-company-tab:hover{ transform:translateY(-2px);box-shadow:0 8px 20px rgba(14,124,134,.15);border-color:var(--ego); }.ego-company-tab.active{ background:linear-gradient(135deg, rgba(14,124,134,0.06), rgba(14,124,134,0.01));border:2px solid var(--ego); }.ego-company-tab .icon-box{ width:50px;height:50px;border-radius:14px;background:rgba(14,124,134,.08);color:var(--ego2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0; }.ego-company-tab.active .icon-box{ background:var(--ego);color:#fff;box-shadow:0 4px 10px rgba(14,124,134,.25); }.ego-company-tab .info .title{ font-weight:800;color:#0f172a;font-size:.95rem;text-transform:uppercase;margin-bottom:2px;line-height:1.3; }.ego-company-tab.active .info .title{ color:var(--ego2); }.ego-company-tab .info .desc{ font-size:.8rem;color:var(--muted); }.ego-company-tab .check-mark{ position:absolute;top:10px;right:12px;color:var(--ego);font-size:1.2rem; }.ego-table-wrap{ overflow-x:auto; }.ego-products-table{ width: max-content; min-width: 1200px; table-layout: auto; }.ego-page-dot{ width:9px;height:9px;border-radius:999px;background:linear-gradient(135deg,var(--ego),var(--ego2));box-shadow:0 8px 18px rgba(14,124,134,.22); }.ego-card{ border:1px solid var(--border);border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.04); }.ego-card-body{ padding:14px; }.ego-label{ font-size:11px;font-weight:900;letter-spacing:.35px;color:var(--muted);text-transform:uppercase;margin-bottom:6px; }.ego-inputgroup .input-group-text{ border-radius:12px 0 0 12px;border:1px solid var(--border);background:rgba(14,124,134,.06);color:var(--ego2);padding:.45rem .6rem; }.ego-inputgroup .pi-input{ border-radius:0 12px 12px 0;border:1px solid var(--border);padding:.45rem .7rem;font-size:.92rem; }.ego-select{ border-radius:12px;border:1px solid var(--border);padding:.45rem .7rem;font-size:.92rem; }.ego-btn-primary{ background:linear-gradient(135deg,var(--ego),var(--ego2));border:none;color:#fff;border-radius:12px;padding:8px 12px;font-weight:900;box-shadow:0 10px 22px rgba(14,124,134,.16);display:inline-flex;align-items:center;gap:8px;font-size:.92rem; }.ego-btn-primary:hover{ filter:brightness(.98);color:#fff; }.ego-btn-soft{ background:rgba(14,124,134,.10);border:1px solid var(--border);color:var(--ego2);border-radius:12px;padding:8px 12px;font-weight:900;display:inline-flex;align-items:center;gap:8px;font-size:.92rem; }.ego-products-table thead th{ background:linear-gradient(135deg, rgba(14,124,134,.10), rgba(14,124,134,.03));border-bottom:1px solid var(--border);font-size:11px;font-weight:900;letter-spacing:.35px;color:var(--muted);text-transform:uppercase;white-space:nowrap;vertical-align:middle;padding:12px 10px;position:sticky;top:0;z-index:1; }.ego-products-table tbody td{ border-top:1px solid rgba(15,23,42,.06);padding:10px 10px;vertical-align:middle;background:#fff;font-size:.93rem; }.ego-products-table tbody tr:nth-child(2n) td{ background:rgba(15,23,42,.015); }.ego-products-table tbody tr:hover td{ background:rgba(14,124,134,.05);transition:background .15s ease; }.ego-pricehead{ display:flex;justify-content:center;align-items:center;padding:2px 0; }.ego-tier-select{ width:150px;border-radius:10px;border-color:rgba(0,0,0,.12);background:#fff;box-shadow:0 1px 0 rgba(0,0,0,.02);font-weight:900;font-size:.85rem;padding:.35rem .5rem; }.ego-stt{ display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:28px;padding:0 10px;border-radius:999px;background:rgba(14,124,134,.10);color:var(--ego2);font-weight:900;font-size:.85rem; }.ego-name{ color:#0f172a;line-height:1.2; }.ego-note{ color:#111827;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.25rem;max-width:720px; }.ego-money{ font-weight:900;letter-spacing:.2px; }.ego-qty{ display:inline-flex;padding:5px 9px;border-radius:999px;border:1px solid rgba(15,23,42,.08);background:#fff;font-weight:900;min-width:58px;justify-content:center;font-size:.9rem; }.ego-qty.is-zero{ color:#b4232c;border-color:rgba(220,53,69,.25);background:rgba(220,53,69,.06); }.ego-badge{ display:inline-flex;padding:5px 9px;border-radius:999px;background:rgba(13,202,240,.14);color:#075d6d;border:1px solid rgba(13,202,240,.22);font-weight:900;font-size:11px;white-space:nowrap; }.ego-badge-brand{ background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.20);color:#3730a3; }.ego-thumb img{ transition:transform .15s ease; }.ego-thumb:hover img{ transform:scale(1.06); }.ego-btn-warn{ border-radius:12px;font-weight:900;border:1px solid rgba(255,193,7,.35);background:rgba(255,193,7,.12);color:#7a5b00;display:inline-flex;align-items:center;gap:6px;padding:7px 10px;font-size:.88rem;white-space:nowrap; }.ego-btn-danger{ border-radius:12px;font-weight:900;border:1px solid rgba(220,53,69,.35);background:rgba(220,53,69,.10);color:#b4232c;display:inline-flex;align-items:center;gap:6px;padding:7px 10px;font-size:.88rem;white-space:nowrap; }.ego-sku{ display:inline-flex;padding:5px 10px;border-radius:999px;border:1px solid rgba(15,23,42,.10);background:rgba(255,255,255,.85);font-weight:900;font-size:12px;color:#0f172a;white-space:nowrap; }@media (max-width: 991.98px){.ego-products-table thead th{ position:static; }.ego-products-table{ min-width:980px; }
  }
</style>
@endsection
