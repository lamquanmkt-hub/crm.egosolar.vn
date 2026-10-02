<x-assets.field label="Mã tài sản"><x-assets.input name="code" :value="$form->code" placeholder="Tự sinh nếu bỏ trống" /></x-assets.field>
<x-assets.field label="Tên tài sản *" span="2"><x-assets.input name="name" :value="$form->name" required placeholder="VD: Laptop Dell, Xe nâng, Máy hàn..." /></x-assets.field>
<x-assets.field label="Nhóm"><x-assets.select name="category_id"><option value="">Chọn nhóm</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected($form->categoryId === (string) $c->id)>{{ $c->name }}</option>@endforeach</x-assets.select></x-assets.field>

<x-assets.field label="Công ty"><x-assets.select name="company_id"><option value="">Chọn công ty</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected($form->companyId === (string) $c->id)>{{ $c->name }}</option>@endforeach</x-assets.select></x-assets.field>
<x-assets.field label="Người đang giữ"><x-assets.select name="assigned_to"><option value="">Chưa bàn giao</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected($form->assignedTo === (string) $u->id)>{{ $u->name }}</option>@endforeach</x-assets.select></x-assets.field>
<x-assets.field label="Bộ phận"><x-assets.input name="department" :value="$form->department" placeholder="Kế toán, kỹ thuật..." /></x-assets.field>
<x-assets.field label="Serial / IMEI"><x-assets.input name="serial_no" :value="$form->serialNo" placeholder="Số serial" /></x-assets.field>

<x-assets.field label="Ngày mua"><x-assets.input type="date" name="purchase_date" :value="$form->purchaseDate" /></x-assets.field>
<x-assets.field label="Ngày bắt đầu dùng"><x-assets.input type="date" name="start_use_date" :value="$form->startUseDate" /></x-assets.field>
<x-assets.field label="Bảo hành đến"><x-assets.input type="date" name="warranty_until" :value="$form->warrantyUntil" /></x-assets.field>
<x-assets.field label="Bảo trì tiếp theo"><x-assets.input type="date" name="next_maintenance_date" :value="$form->nextMaintenanceDate" /></x-assets.field>

<x-assets.field label="Nguyên giá"><x-assets.input name="original_cost" :value="$form->originalCost" inputmode="decimal" data-money x-on:input="$el.value = $el.value.replace(/[^0-9.,-]/g, '')" placeholder="VD: 15000000" /></x-assets.field>
<x-assets.field label="Giá trị còn lại tối thiểu"><x-assets.input name="salvage_value" :value="$form->salvageValue" inputmode="decimal" data-money x-on:input="$el.value = $el.value.replace(/[^0-9.,-]/g, '')" /></x-assets.field>
<x-assets.field label="Thời gian KH" hint="Đơn vị: tháng"><x-assets.input type="number" min="1" max="600" name="useful_life_months" :value="$form->usefulLifeMonths" /></x-assets.field>
<x-assets.field label="Phương pháp KH"><x-assets.select name="depreciation_method"><option value="straight_line" @selected($form->depreciationMethod === 'straight_line')>Đường thẳng</option></x-assets.select></x-assets.field>

<x-assets.field label="Trạng thái"><x-assets.select name="status">@foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected($form->status === $k)>{{ $v }}</option>@endforeach</x-assets.select></x-assets.field>
<x-assets.field label="Tình trạng"><x-assets.select name="condition">@foreach($conditions as $k=>$v)<option value="{{ $k }}" @selected($form->condition === $k)>{{ $v }}</option>@endforeach</x-assets.select></x-assets.field>
<x-assets.field label="Nhà cung cấp"><x-assets.input name="vendor" :value="$form->vendor" /></x-assets.field>
<x-assets.field label="Số hóa đơn/CT"><x-assets.input name="invoice_no" :value="$form->invoiceNo" /></x-assets.field>

<x-assets.field label="Vị trí" span="2"><x-assets.input name="location" :value="$form->location" placeholder="Văn phòng, kho, công trình..." /></x-assets.field>
<x-assets.field label="File chứng từ" span="2"><x-assets.input type="file" name="files[]" multiple /></x-assets.field>
<x-assets.field label="Ghi chú" span="4"><textarea class="tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:bg-white tw:text-[#0f172a] tw:font-bold tw:outline-none tw:min-h-[76px] tw:px-[13px] tw:py-3 tw:resize-y" name="note" placeholder="Thông tin mua, bàn giao, cấu hình, lưu ý...">{{ $form->note }}</textarea></x-assets.field>
