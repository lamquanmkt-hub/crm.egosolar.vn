{{-- Lưu ý: trong view của component KHÔNG có `$this`; phương thức public của lớp
     được Laravel truyền vào dưới dạng closure, nên gọi là `$classes()`. --}}
@if ($as !== '')
    <{{ $as }} {{ $attributes->class($classes()) }}>{{ $slot }}</{{ $as }}>
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes()) }}>{{ $slot }}</a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" {{ $attributes->except('type')->class($classes()) }}>{{ $slot }}</button>
@endif
