{{-- Trong view của component KHÔNG có $this; phương thức public được truyền vào
     dưới dạng closure nên gọi là $classes(). --}}
{{-- `data-ego-alert` là móc để nút đóng tìm được hộp mà không cần lớp `.alert`
     của Bootstrap. Xem `wireAlert()` trong resources/js/bs-compat/alert.js. --}}
<div data-ego-alert {{ $attributes->class($classes($attributes->get('class', ''))) }} role="alert">
    {{ $slot }}

    @if ($dismissible)
        {{-- Định vị `.alert-dismissible .btn-close` của Bootstrap (absolute; top/right 0;
             z-index 2; đệm 20px 16px) nay nằm trong component qua in="alert". Thiếu nó
             thì nút nằm trong dòng chảy và hộp cao thêm 2px — đã đo. Hành vi đóng do
             resources/js/bs-compat/alert.js lo, không phải Bootstrap JS. --}}
            <x-ui.close-button in="alert" data-bs-dismiss="alert" aria-label="Đóng" />
    @endif
</div>
