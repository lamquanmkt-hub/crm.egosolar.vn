@php
    $action = $action ?? route('warehouses.store');
    $method = $method ?? 'POST';
    $warehouse = $warehouse ?? null;
@endphp

<section class="ego-inventory-form-card">
    <div class="ego-inventory-form-card__head">
        <span class="ego-inventory-section-icon"><i class="bi bi-buildings"></i></span>
        <div><h2>Thông tin kho</h2><p>Cập nhật tên, vị trí và phạm vi quản lý kho.</p></div>
    </div>
    <form action="{{ $action }}" method="POST" class="ego-inventory-form-card__body">
        @csrf
        @if(strtoupper($method) === 'PUT') @method('PUT') @endif
        @if ($errors->any())
            {{-- CHƯA chuyển sang <x-ui.alert>: partial này chỉ được include từ
                 warehouses/create và warehouses/edit, hai trang bọc trong
                 `.ego-inventory-enterprise`. `public/css/ego-inventory-enterprise.css` có
                 `.ego-inventory-enterprise .alert{border-radius:12px!important;font-size:12px
                 !important;font-weight:650!important;…}` — bỏ lớp `.alert` là mất hết. Chuyển
                 được khi luật đó chuyển theo. --}}
            <x-ui.alert variant="danger" class="tw:rounded-[12px] tw:text-[12px] tw:[font-weight:650]"><strong>Chưa lưu được kho</strong><ul class="tw:mb-0 tw:mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>
        @endif
        <div class="ego-inventory-form-grid">
            <div class="ego-inventory-field ego-inventory-field--wide">
                <label>Công ty</label>
                <input type="hidden" name="company_ids[]" value="1">
                <div class="ego-inventory-readonly"><i class="bi bi-building-check"></i><span>CÔNG TY TNHH EGO VIỆT NAM</span></div>
            </div>
            <div class="ego-inventory-field">
                <label for="warehouseName">Tên kho <b>*</b></label>
                <x-ui.input id="warehouseName" class="ego-input" type="text" name="name" value="{{ old('name', $warehouse->name ?? '') }}" placeholder="Ví dụ: Kho EGO_VN" required />
            </div>
            <div class="ego-inventory-field">
                <label for="warehouseLocation">Địa điểm</label>
                <x-ui.input id="warehouseLocation" class="ego-input" type="text" name="location" value="{{ old('location', $warehouse->location ?? '') }}" placeholder="Nhập địa chỉ hoặc khu vực kho" />
            </div>
        </div>
        <div class="ego-inventory-form-actions">
            <a href="{{ route('warehouses.index') }}" class="ego-inventory-btn ego-inventory-btn--secondary">Hủy</a>
            <button type="submit" class="ego-inventory-btn ego-inventory-btn--primary"><i class="bi bi-check2-circle"></i>{{ strtoupper($method) === 'PUT' ? 'Lưu thay đổi' : 'Tạo kho' }}</button>
        </div>
    </form>
</section>
