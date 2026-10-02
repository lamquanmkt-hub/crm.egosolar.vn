{{-- Khung "đang cập nhật" dùng chung cho các trang báo cáo chưa có nội dung.
     Biến: $tieuDe (bắt buộc), $moTa (tuỳ chọn). --}}
<div class="tw:mx-auto tw:max-w-[560px] tw:px-6 tw:py-16 tw:text-center">
    <div class="tw:mx-auto tw:mb-5 tw:flex tw:h-16 tw:w-16 tw:items-center tw:justify-center tw:rounded-full tw:bg-[#eef4fb]">
        <i class="bi bi-tools tw:text-[28px] tw:text-[#5b7a99]"></i>
    </div>

    <h1 class="tw:mb-2 tw:text-[20px]/[28px] tw:font-bold tw:text-[#1f2d3d]">{{ $tieuDe }}</h1>

    <p class="tw:mb-6 tw:text-[14px]/[22px] tw:text-[#66788a]">
        {{ $moTa ?? 'Trang này đang được xây dựng, sẽ có nội dung trong bản cập nhật tới.' }}
    </p>

    <x-ui.button variant="outline-secondary" :href="route('marketing.dashboard')">
        <i class="bi bi-arrow-left"></i> Về bảng điều khiển Marketing
    </x-ui.button>
</div>
