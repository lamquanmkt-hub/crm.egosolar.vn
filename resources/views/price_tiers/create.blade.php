@extends('layouts.app')
@section('title', 'Thêm loại giá')
@section('content')
    {{-- Trang thí điểm chuyển sang Tailwind (2026-09-02). Giá trị dưới đây LẤY TỪ COMPUTED
         STYLE ĐANG CHẠY, không lấy mặc định của Tailwind:
           .container-fluid.px-4 -> padding ngang 24px  => tw:px-6
           .mb-3                 -> 16px               => tw:mb-4  (tw:mb-3 chỉ 12px!)
           .card-body            -> padding 16px        => tw:p-4
         Card giữ nguyên các biến CSS thay vì đóng cứng số: `--ego-theme-radius` là thiết
         lập admin đổi được (8–30px) qua trang cài đặt; đóng cứng 16px sẽ làm trang này
         lặng lẽ ngừng theo theme. `--border`/`--card` khai trong <style> của layout.
         Nút "Quay lại" dùng <x-ui.button> — xem app/View/Components/Ui/Button.php,
         nơi ghi rõ 4 trạng thái đo được của .btn-outline-secondary. --}}
    <div class="tw:w-full tw:px-6">
        <div class="tw:mb-4 tw:flex tw:items-center tw:justify-between">
            <h1 class="tw:mb-0 tw:font-bold tw:uppercase tw:text-[rgb(108,117,125)]">THÊM LOẠI GIÁ</h1>
            <x-ui.button :href="route('price-tiers.index')" variant="outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </x-ui.button>
        </div>
        <div class="tw:relative tw:flex tw:min-w-0 tw:flex-col tw:break-words tw:rounded-[var(--ego-theme-radius,16px)] tw:border tw:border-solid tw:border-[var(--border)] tw:bg-[var(--card)] tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
            <div class="tw:flex-auto tw:p-4">
                <form method="POST" action="{{ route('price-tiers.store') }}">
                    @include('price_tiers._form', ['tier' => null, 'buttonText' => 'Tạo loại giá'])
                </form>
            </div>
        </div>
    </div>
@endsection
