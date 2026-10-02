@extends('layouts.app')

@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    {{-- `.container-fluid` = width 100% + đệm 12px + margin auto; trang đã có `tw:px-6` (24px) nên
         đệm 12px của container vốn đã bị đè — giữ nguyên thứ tự để không đổi gì. --}}
    <div class="tw:w-full tw:mx-auto tw:px-6 tw:py-4">
        <div class="tw:flex tw:justify-between tw:items-center tw:mb-6">
            <h1 class="tw:font-bold tw:uppercase tw:text-[#6c757d]">Chi tiết danh mục sản phẩm</h1>
            <div>
                <x-ui.button href="{{ route('categories.edit', $categoryDetail->id) }}" variant="warning">
                    <i class="bi bi-pencil"></i> Chỉnh sửa
                </x-ui.button>
                <x-ui.button href="{{ route('categories.index') }}" variant="outline-secondary">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </x-ui.button>
            </div>
        </div>

        <div class="tw:row">
            <div class="tw:md:col12-8">
                <x-ui.card class="tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:mb-6">
                    <x-ui.card-header class="tw:bg-[rgb(13,110,253)] tw:text-[#ffffff]">
                        <h5 class="tw:mb-0">Thông tin danh mục</h5>
                    </x-ui.card-header>
                    <x-ui.card-body>
                        <dl class="tw:row">
                            <dt class="tw:min-[36rem]:col12-3">ID:</dt>
                            <dd class="tw:min-[36rem]:col12-9">{{ $categoryDetail->id }}</dd>

                            <dt class="tw:min-[36rem]:col12-3">Tên danh mục:</dt>
                            <dd class="tw:min-[36rem]:col12-9"><strong>{{ $categoryDetail->name }}</strong></dd>

                            <dt class="tw:min-[36rem]:col12-3">Mô tả:</dt>
                            <dd class="tw:min-[36rem]:col12-9">{{ $categoryDetail->descriptionText }}</dd>

                            <dt class="tw:min-[36rem]:col12-3">Danh mục cha:</dt>
                            <dd class="tw:min-[36rem]:col12-9">
                                @if($categoryDetail->parentName !== null)
                                    <span class="tw:inline-block tw:px-[0.65em] tw:py-[0.35em] tw:text-[0.75em] tw:font-bold tw:leading-none tw:text-center tw:whitespace-nowrap tw:align-baseline tw:text-white tw:rounded-[0.375rem] tw:bg-[rgb(13,202,240)]">{{ $categoryDetail->parentName }}</span>
                                @else
                                    <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
                                @endif
                            </dd>

                            <dt class="tw:min-[36rem]:col12-3">Ngày tạo:</dt>
                            <dd class="tw:min-[36rem]:col12-9">{{ $categoryDetail->createdText }}</dd>

                            <dt class="tw:min-[36rem]:col12-3">Cập nhật lần cuối:</dt>
                            <dd class="tw:min-[36rem]:col12-9">{{ $categoryDetail->updatedText }}</dd>
                        </dl>
                    </x-ui.card-body>
                </x-ui.card>

                @if($categoryDetail->childCount > 0)
                    <x-ui.card class="tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:mb-6">
                        <x-ui.card-header class="tw:bg-[rgb(108,117,125)] tw:text-[#ffffff]">
                            <h5 class="tw:mb-0">Danh mục con ({{ $categoryDetail->childCount }})</h5>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            {{-- `.list-group` + `.list-group-item` tái hiện TẠI TRANG (chỉ trang này dùng, không dựng
                                 component chung — YAGNI). Số đo từ bootstrap@5.3.3: item đệm .5rem/1rem, chữ #212529,
                                 nền #fff, viền 1px solid #dee2e6, và `item + item{border-top-width:0}`. --}}
                            <ul class="tw:flex tw:flex-col tw:pl-0 tw:mb-0 tw:rounded-[0.375rem] tw:[&>li:first-child]:[border-top-left-radius:inherit] tw:[&>li:first-child]:[border-top-right-radius:inherit] tw:[&>li:last-child]:[border-bottom-right-radius:inherit] tw:[&>li:last-child]:[border-bottom-left-radius:inherit]">
                                @foreach($categoryDetail->children as $child)
                                    <li class="tw:relative tw:block tw:py-2 tw:px-4 tw:text-[#212529] tw:no-underline tw:bg-white tw:[border:1px_solid_#dee2e6] tw:[&+*]:border-t-0 tw:flex tw:justify-between tw:items-center">
                                        <a href="{{ route('categories.show', $child->id) }}">{{ $child->name }}</a>
                                        <span class="tw:inline-block tw:px-[0.65em] tw:py-[0.35em] tw:text-[0.75em] tw:font-bold tw:leading-none tw:text-center tw:whitespace-nowrap tw:align-baseline tw:text-white tw:rounded-[50rem] tw:bg-[rgb(13,110,253)]">{{ $child->productCount }} sản phẩm</span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-ui.card-body>
                    </x-ui.card>
                @endif
            </div>

            <div class="tw:md:col12-4">
                <x-ui.card class="tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)]">
                    <x-ui.card-header class="tw:bg-[rgb(13,202,240)] tw:text-[#ffffff]">
                        <h5 class="tw:mb-0">Thống kê</h5>
                    </x-ui.card-header>
                    <x-ui.card-body>
                        <div class="tw:mb-4">
                            <h6>Số sản phẩm</h6>
                            <p class="tw:text-[calc(1.3rem+0.6vw)] tw:min-[75rem]:text-[1.75rem] tw:mt-0 tw:mb-2 tw:font-medium tw:leading-[1.2] tw:text-[#0d6efd]">{{ $categoryDetail->productCount }}</p>
                        </div>
                        <div class="tw:mb-4">
                            <h6>Số danh mục con</h6>
                            <p class="tw:text-[calc(1.3rem+0.6vw)] tw:min-[75rem]:text-[1.75rem] tw:mt-0 tw:mb-2 tw:font-medium tw:leading-[1.2] tw:text-[#6c757d]">{{ $categoryDetail->childCount }}</p>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>

                @if($categoryDetail->productCount > 0)
                    <x-ui.card class="tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:mt-6">
                        <x-ui.card-header class="tw:bg-[rgb(25,135,84)] tw:text-[#ffffff]">
                            <h5 class="tw:mb-0">Sản phẩm trong danh mục</h5>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            {{-- Bản `flush`: `border-radius:0` và item chỉ còn viền DƯỚI, item cuối bỏ luôn. --}}
                            <ul class="tw:flex tw:flex-col tw:pl-0 tw:mb-0 tw:rounded-none tw:[&>li]:[border-width:0_0_1px] tw:[&>li:last-child]:[border-bottom-width:0]">
                                @foreach($categoryDetail->products as $product)
                                    <li class="tw:relative tw:block tw:py-2 tw:px-4 tw:text-[#212529] tw:no-underline tw:bg-white tw:[border:1px_solid_#dee2e6] tw:[&+*]:border-t-0">
                                        <a href="{{ route('products.edit', $product['id']) }}">{{ $product['name'] }}</a>
                                    </li>
                                @endforeach
                                @if($categoryDetail->extraProductCount > 0)
                                    <li class="tw:relative tw:block tw:py-2 tw:px-4 tw:text-[#212529] tw:no-underline tw:bg-white tw:[border:1px_solid_#dee2e6] tw:[&+*]:border-t-0 tw:text-center">
                                        <small class="tw:text-[rgba(33,37,41,0.75)]">... và {{ $categoryDetail->extraProductCount }} sản phẩm khác</small>
                                    </li>
                                @endif
                            </ul>
                        </x-ui.card-body>
                    </x-ui.card>
                @endif
            </div>
        </div>
    </div>
@endsection

