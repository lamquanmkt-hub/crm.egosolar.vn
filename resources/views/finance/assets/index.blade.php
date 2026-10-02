@extends('layouts.app')

@section('title', 'Tài sản')

@section('content')
<div x-data="{ moThem: false }" class="tw:min-h-full tw:pt-6 tw:px-7 tw:pb-[42px] tw:text-[#0f172a] tw:bg-[linear-gradient(180deg,#f7fbff_0%,#f8fafc_45%,#fff_100%)]">
    <div class="tw:flex tw:items-start tw:justify-between tw:gap-4 tw:mb-[18px] tw:max-[1201px]:block">
        <div>
            <h1 class="tw:m-0 tw:text-[28px] tw:[font-weight:950] tw:tracking-[-0.04em]">💼 Tài sản</h1>
            <p class="tw:[margin:7px_0_0] tw:text-[#64748b] tw:text-[14px]">Quản lý tài sản cố định, công cụ dụng cụ, bàn giao, bảo trì, file chứng từ và khấu hao tự động.</p>
        </div>
        <div class="tw:flex tw:gap-[10px] tw:flex-wrap tw:justify-end tw:max-[1201px]:justify-start tw:max-[1201px]:mt-3">
            <x-assets.btn tone="light" :href="route('finance.assets.export')">⬇ Xuất CSV</x-assets.btn>
            <x-assets.btn tone="primary" type="button" aria-controls="assetCreateCard"
                x-on:click="moThem = ! moThem; if (moThem) $nextTick(() => $refs.theThem?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                x-bind:aria-expanded="moThem ? 'true' : 'false'">+ Thêm tài sản</x-assets.btn>
        </div>
    </div>

    @if(session('success')) <div class="tw:rounded-[14px] tw:px-[14px] tw:py-3 tw:font-extrabold tw:mb-[14px] tw:bg-[#dcfce7] tw:text-[#047857]">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="tw:rounded-[14px] tw:px-[14px] tw:py-3 tw:font-extrabold tw:mb-[14px] tw:bg-[#ffe4e6] tw:text-[#be123c]">{{ $errors->first() }}</div> @endif

    <div class="tw:grid tw:grid-cols-[repeat(5,minmax(0,1fr))] tw:gap-[14px] tw:mb-4 tw:max-[1201px]:grid-cols-2 tw:max-[641px]:grid-cols-1">
        <div class="tw:bg-white tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-[18px] tw:shadow-[0_16px_35px_rgba(15,23,42,0.05)]"><div class="tw:text-[12px] tw:font-black tw:text-[#64748b] tw:uppercase tw:tracking-[0.03em]">Tổng tài sản</div><div class="tw:text-[22px] tw:[font-weight:950] tw:mt-2 tw:tracking-[-0.035em]">{{ $summaryCards->countText }}</div><div class="tw:text-[12px] tw:text-[#64748b] tw:mt-1 tw:font-bold">{{ $summaryCards->activeText }} đang sử dụng</div></div>
        <div class="tw:bg-white tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-[18px] tw:shadow-[0_16px_35px_rgba(15,23,42,0.05)]"><div class="tw:text-[12px] tw:font-black tw:text-[#64748b] tw:uppercase tw:tracking-[0.03em]">Nguyên giá</div><div class="tw:text-[22px] tw:[font-weight:950] tw:mt-2 tw:tracking-[-0.035em]">{{ $summaryCards->costText }}</div><div class="tw:text-[12px] tw:text-[#64748b] tw:mt-1 tw:font-bold">Tổng giá trị ghi nhận</div></div>
        <div class="tw:bg-white tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-[18px] tw:shadow-[0_16px_35px_rgba(15,23,42,0.05)]"><div class="tw:text-[12px] tw:font-black tw:text-[#64748b] tw:uppercase tw:tracking-[0.03em]">Giá trị còn lại</div><div class="tw:text-[22px] tw:[font-weight:950] tw:mt-2 tw:tracking-[-0.035em] tw:text-[#059669]">{{ $summaryCards->bookValueText }}</div><div class="tw:text-[12px] tw:text-[#64748b] tw:mt-1 tw:font-bold">Theo khấu hao đường thẳng</div></div>
        <div class="tw:bg-white tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-[18px] tw:shadow-[0_16px_35px_rgba(15,23,42,0.05)]"><div class="tw:text-[12px] tw:font-black tw:text-[#64748b] tw:uppercase tw:tracking-[0.03em]">Khấu hao lũy kế</div><div class="tw:text-[22px] tw:[font-weight:950] tw:mt-2 tw:tracking-[-0.035em] tw:text-[#f59e0b]">{{ $summaryCards->accumulatedText }}</div><div class="tw:text-[12px] tw:text-[#64748b] tw:mt-1 tw:font-bold">Khấu hao dự kiến hiện tại</div></div>
        <div class="tw:bg-white tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-[18px] tw:shadow-[0_16px_35px_rgba(15,23,42,0.05)]"><div class="tw:text-[12px] tw:font-black tw:text-[#64748b] tw:uppercase tw:tracking-[0.03em]">Cần chú ý</div><div class="tw:text-[22px] tw:[font-weight:950] tw:mt-2 tw:tracking-[-0.035em] tw:text-[#e11d48]">{{ $summaryCards->warningText }}</div><div class="tw:text-[12px] tw:text-[#64748b] tw:mt-1 tw:font-bold">Bảo trì/hết hạn trong 30 ngày</div></div>
    </div>

    <x-assets.card class="tw:mb-4">
        <form class="tw:grid tw:grid-cols-[1.3fr_150px_180px_180px_155px_auto_auto] tw:gap-[10px] tw:p-4 tw:max-[1201px]:grid-cols-2 tw:max-[641px]:grid-cols-1" method="GET" action="{{ route('finance.assets.index') }}">
            <x-assets.input name="keyword" :value="$filters['keyword'] ?? ''" placeholder="Tìm mã, tên, serial, nhà cung cấp, vị trí..." />
            <x-assets.select name="status"><option value="">Tất cả trạng thái</option>@foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(($filters['status'] ?? '')===$k)>{{ $v }}</option>@endforeach</x-assets.select>
            <x-assets.select name="category_id"><option value="0">Tất cả nhóm</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category_id'] ?? 0)==$c->id)>{{ $c->name }}</option>@endforeach</x-assets.select>
            <x-assets.select name="company_id"><option value="0">Tất cả công ty</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected(($filters['company_id'] ?? 0)==$c->id)>{{ $c->name }}</option>@endforeach</x-assets.select>
            <x-assets.select name="condition"><option value="">Tất cả tình trạng</option>@foreach($conditions as $k=>$v)<option value="{{ $k }}" @selected(($filters['condition'] ?? '')===$k)>{{ $v }}</option>@endforeach</x-assets.select>
            <x-assets.btn tone="primary" type="submit">Lọc</x-assets.btn>
            <x-assets.btn tone="light" :href="route('finance.assets.index')">Reset</x-assets.btn>
        </form>
    </x-assets.card>

    <x-assets.card id="assetCreateCard" class="tw:mb-4" x-ref="theThem" x-show="moThem" x-cloak>
        <x-assets.card-head title="Thêm tài sản mới" sub="Có thể thêm file hóa đơn, ảnh, biên bản bàn giao.">
            <x-assets.btn tone="light" type="button" x-on:click="moThem = false">Đóng</x-assets.btn>
        </x-assets.card-head>
        <form class="tw:grid tw:grid-cols-[repeat(4,minmax(0,1fr))] tw:gap-3 tw:p-4 tw:max-[1201px]:grid-cols-2 tw:max-[641px]:grid-cols-1" method="POST" action="{{ route('finance.assets.store') }}" enctype="multipart/form-data">
            @csrf
            @include('finance.assets.partials.form-fields', ['form' => $createForm])
            <div class="tw:[grid-column:span_4] tw:max-[1201px]:[grid-column:span_2] tw:max-[641px]:[grid-column:span_1]"><x-assets.btn tone="green" type="submit">Lưu tài sản</x-assets.btn></div>
        </form>
        <form class="tw:grid tw:grid-cols-[1fr_120px_130px_100px_auto] tw:gap-2 tw:px-4 tw:py-[14px] tw:[border-top:1px_solid_#eef2f7] tw:bg-[#fbfdff] tw:max-[1201px]:grid-cols-2 tw:max-[641px]:grid-cols-1" method="POST" action="{{ route('finance.assets.categories.store') }}">
            @csrf
            <x-assets.input name="name" placeholder="Thêm nhanh nhóm tài sản" />
            <x-assets.input name="code" placeholder="Mã nhóm" />
            <x-assets.input name="useful_life_months" type="number" min="1" placeholder="Số tháng KH" />
            <x-assets.input name="color" value="#0ea5e9" placeholder="Màu" />
            <x-assets.btn tone="light" type="submit">+ Nhóm</x-assets.btn>
        </form>
    </x-assets.card>

    <x-assets.card class="tw:mb-4">
        <x-assets.card-head title="Danh sách tài sản" sub="Bấm mắt để xem chi tiết, bút để sửa, đồng hồ để ghi lịch sử/bảo trì/bàn giao." />
        <div class="tw:overflow-auto">
            <table class="tw:w-full tw:[border-collapse:separate] tw:[border-spacing:0] tw:min-w-[1280px]">
                <thead>
                    <tr>
                        <th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">STT</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Mã / Tên</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Nhóm</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Công ty / Người dùng</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Nguyên giá</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Còn lại</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">KH tháng</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Tiến độ</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Trạng thái</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Bảo trì</th><th scope="col" class="tw:px-3 tw:py-[13px] tw:bg-[#f8fbff] tw:text-[#475569] tw:text-[12px] tw:uppercase tw:text-left tw:[border-bottom:1px_solid_#e5edf7] tw:whitespace-nowrap">Thao tác</th>
                    </tr>
                </thead>
                <tbody x-data="{ moChiTiet: {} }">
                    @forelse($assetRows as $row)
                        <tr class="tw:hover:[&>td]:bg-[#fbfdff]">
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle">{{ $row->stt }}</td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><div class="tw:[font-weight:950] tw:text-[#0f172a]">{{ $row->code }}</div><div class="tw:[font-weight:950]">{{ $row->name }}</div><div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">Serial: {{ $row->serialText }} · Vị trí: {{ $row->locationText }}</div></td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><span class="tw:inline-flex tw:items-center tw:justify-center tw:rounded-[999px] tw:px-[10px] tw:py-[7px] tw:text-[12px] tw:[font-weight:950] tw:whitespace-nowrap tw:bg-[#ecfeff] tw:text-[#0e7490]"><span class="tw:w-[9px] tw:h-[9px] tw:rounded-[999px] tw:inline-block tw:mr-[7px]" style="background:{{ $row->categoryColor }}"></span>{{ $row->categoryName }}</span></td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><div>{{ $row->companyName }}</div><div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">{{ $row->assignedText }}</div></td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle tw:[font-weight:950] tw:text-[#0f766e] tw:whitespace-nowrap">{{ $row->costText }}</td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><div class="tw:[font-weight:950] tw:text-[#0f766e] tw:whitespace-nowrap">{{ $row->bookValueText }}</div><div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">Đã KH: {{ $row->accumulatedText }}</div></td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle tw:[font-weight:950] tw:text-[#0f766e] tw:whitespace-nowrap">{{ $row->monthlyText }}</td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $row->progressPercentText }}"
                                    aria-label="Đã khấu hao {{ $row->usageText }}"
                                    class="tw:h-2 tw:rounded-[999px] tw:bg-[#eef2f7] tw:overflow-hidden tw:min-w-[96px]"><span class="tw:block tw:h-full tw:rounded-[999px] tw:bg-[linear-gradient(90deg,#0ea5e9,#10b981)]" style="width:{{ $row->progressPercentText }}%"></span></div><div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">{{ $row->usageText }}</div></td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><span class="tw:inline-flex tw:items-center tw:justify-center tw:rounded-[999px] tw:px-[10px] tw:py-[7px] tw:text-[12px] tw:[font-weight:950] tw:whitespace-nowrap {{ $row->statusBadgeClass }}">{{ $row->statusLabel }}</span><div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">{{ $row->conditionLabel }}</div></td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><div>{{ $row->nextMaintenanceText }}</div><div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">BH: {{ $row->warrantyText }}</div></td>
                            <td class="tw:px-3 tw:py-[13px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle"><div class="tw:flex tw:gap-[7px] tw:justify-end"><x-assets.icon-btn type="button" title="Xem chi tiết tài sản {{ $row->code }}" aria-label="Xem chi tiết tài sản {{ $row->code }}"
                                    aria-controls="asset-detail-{{ $row->id }}"
                                    x-bind:aria-expanded="moChiTiet[{{ $row->id }}] ? 'true' : 'false'"
                                    x-on:click="moChiTiet[{{ $row->id }}] = ! moChiTiet[{{ $row->id }}]">👁</x-assets.icon-btn><x-assets.icon-btn type="button" title="Sửa tài sản {{ $row->code }}" aria-label="Sửa tài sản {{ $row->code }}"
                                    aria-controls="asset-detail-{{ $row->id }}"
                                    x-on:click="moChiTiet[{{ $row->id }}] = true; $nextTick(() => $refs['chiTiet{{ $row->id }}']?.scrollIntoView({ behavior: 'smooth', block: 'center' }))">✎</x-assets.icon-btn><form method="POST" action="{{ route('finance.assets.destroy', $row->id) }}" onsubmit="return confirm('Xóa tài sản này?')">@csrf @method('DELETE')<x-assets.icon-btn tone="red" type="submit" title="Xóa tài sản {{ $row->code }}" aria-label="Xóa tài sản {{ $row->code }}">×</x-assets.icon-btn></form></div></td>
                        </tr>
                        <tr id="asset-detail-{{ $row->id }}" x-ref="chiTiet{{ $row->id }}" x-show="moChiTiet[{{ $row->id }}]" x-cloak
                            class="tw:bg-[#f8fbff] tw:hover:[&>td]:bg-[#fbfdff]">
                            <td colspan="11" class="tw:p-[18px] tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle">
                                <div class="tw:grid tw:grid-cols-[1.15fr_0.85fr] tw:gap-4 tw:max-[1201px]:grid-cols-1">
                                    <x-assets.card>
                                        <x-assets.card-head title="Sửa tài sản" :sub="'Mã: '.$row->code.' · Giá trị còn lại '.$row->bookValueText" />
                                        <form method="POST" action="{{ route('finance.assets.update', $row->id) }}" enctype="multipart/form-data" class="tw:grid tw:grid-cols-[repeat(4,minmax(0,1fr))] tw:gap-3 tw:p-4 tw:max-[1201px]:grid-cols-2 tw:max-[641px]:grid-cols-1">
                                            @csrf @method('PUT')
                                            @include('finance.assets.partials.form-fields', ['form' => $row->form])
                                            <div class="tw:[grid-column:span_4] tw:max-[1201px]:[grid-column:span_2] tw:max-[641px]:[grid-column:span_1]"><x-assets.btn tone="primary" type="submit">Cập nhật tài sản</x-assets.btn></div>
                                        </form>
                                    </x-assets.card>
                                    <x-assets.card>
                                        <x-assets.card-head title="Lịch sử / bảo trì" sub="Bàn giao, điều chuyển, sửa chữa, thanh lý." />
                                        <div class="tw:p-[14px]">
                                            <div class="tw:grid tw:gap-2 tw:max-h-[360px] tw:overflow-auto">
                                                @forelse($row->events as $event)
                                                    <div class="tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[15px] tw:px-3 tw:py-[10px] tw:bg-white">
                                                        <div class="tw:flex tw:items-center tw:justify-between tw:gap-[10px]"><b>{{ $event->typeLabel }}</b><span class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">{{ $event->dateText }}</span></div>
                                                        <div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">{{ $event->noteText }}</div>
                                                        @if($event->amountText)<div class="tw:[font-weight:950] tw:text-[#0f766e] tw:whitespace-nowrap">{{ $event->amountText }}</div>@endif
                                                    </div>
                                                @empty
                                                    <div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">Chưa có lịch sử.</div>
                                                @endforelse
                                            </div>
                                            <div class="tw:flex tw:flex-wrap tw:gap-2 tw:mt-[10px]">
                                                @foreach($row->files as $file)
                                                    <a class="tw:border tw:border-solid tw:border-[#dbeafe] tw:rounded-[999px] tw:px-[10px] tw:py-[7px] tw:bg-white tw:no-underline tw:text-[12px] tw:font-black tw:text-[#2563eb]" href="{{ route('finance.assets.files.download', $file->id) }}">📎 {{ $file->name }}</a>
                                                @endforeach
                                            </div>
                                            <form method="POST" action="{{ route('finance.assets.events.store', $row->id) }}" class="tw:grid tw:grid-cols-[150px_140px_130px_1fr_150px_auto] tw:gap-2 tw:mt-3 tw:max-[1201px]:grid-cols-2 tw:max-[641px]:grid-cols-1">
                                                @csrf
                                                <x-assets.select name="type">@foreach($eventTypes as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-assets.select>
                                                <x-assets.input type="date" name="event_date" :value="now()->format('Y-m-d')" />
                                                <x-assets.input name="amount" inputmode="decimal" placeholder="Chi phí" />
                                                <x-assets.input name="to_location" placeholder="Vị trí mới / nơi bảo trì" />
                                                <x-assets.select name="status"><option value="">Giữ trạng thái</option>@foreach($statuses as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-assets.select>
                                                <x-assets.input type="date" name="next_maintenance_date" title="Lịch bảo trì tiếp theo" />
                                                <x-assets.select name="to_user_id"><option value="">Người nhận</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</x-assets.select>
                                                <x-assets.select name="condition"><option value="">Giữ tình trạng</option>@foreach($conditions as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-assets.select>
                                                <x-assets.input name="note" placeholder="Ghi chú lịch sử" class="tw:[grid-column:span_3] tw:max-[1201px]:[grid-column:span_2] tw:max-[641px]:[grid-column:span_1]" />
                                                <x-assets.btn tone="green" type="submit">+ Ghi</x-assets.btn>
                                            </form>
                                        </div>
                                    </x-assets.card>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="tw:hover:[&>td]:bg-[#fbfdff]"><td colspan="11" class="tw:p-9 tw:[border-bottom:1px_solid_#edf2f7] tw:align-middle tw:text-center tw:text-[#64748b] tw:font-black">Chưa có tài sản nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="tw:px-4 tw:py-[14px]">{{ $assets->links() }}</div>
    </x-assets.card>
</div>
@endsection
