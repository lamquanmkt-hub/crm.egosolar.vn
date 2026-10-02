{{-- Một ô của biểu mẫu tài sản (`.ap-field` + `label` + `small` cũ, 21 chỗ dùng).

     ⚠️ Nhãn BỌC ô nhập (implicit label) thay vì đứng cạnh nó. Bản cũ dùng `<label>` trần, không
     `for` và không bọc, nên bấm vào nhãn không đưa được tiêu điểm vào ô. Không dùng `for="id"`
     được: partial biểu mẫu render N+1 lần trên cùng một trang (một lần thêm mới + một lần cho MỖI
     dòng tài sản) nên id sẽ trùng hàng chục lần.

     `span` chỉ nhận '2'/'4' vì lưới cha là 4 cột; ở ≤1200px lưới còn 2 cột và ≤640px còn 1 cột nên
     bề rộng ô phải co theo, nếu không ô span-4 sẽ tràn khỏi lưới. --}}
@props(['label', 'hint' => '', 'span' => ''])

<div @class([
    'tw:[grid-column:span_2] tw:max-[641px]:[grid-column:span_1]' => $span === '2',
    'tw:[grid-column:span_4] tw:max-[1201px]:[grid-column:span_2] tw:max-[641px]:[grid-column:span_1]' => $span === '4',
])>
    <label class="tw:block">
        <span class="tw:block tw:text-[11px] tw:uppercase tw:text-[#64748b] tw:[font-weight:950] tw:[margin:0_0_6px]">{{ $label }}</span>
        {{ $slot }}
    </label>
    @if($hint !== '')<small class="tw:block tw:text-[#64748b] tw:font-bold tw:mt-[5px]">{{ $hint }}</small>@endif
</div>
