
@extends('layouts.app')

@section('title', ($scope ?? 'all') === 'domestic' ? 'Phải trả nhà cung cấp trong nước' : ((($scope ?? 'all') === 'import') ? 'Phải trả hàng nhập khẩu' : 'Công nợ nhà cung cấp'))

@section('content')
<div class="tw:pt-6 tw:px-7 tw:pb-9 tw:max-[769px]:p-4 tw:min-h-full tw:text-[#0f172a] tw:[background:linear-gradient(180deg,#f7fbff_0%,#f8fafc_42%,#ffffff_100%)]">
    @if(session('success'))
        <div class="tw:mb-[14px] tw:py-3 tw:px-[14px] tw:rounded-[14px] tw:font-extrabold tw:bg-[#dcfce7] tw:text-[#047857]">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="tw:mb-[14px] tw:py-3 tw:px-[14px] tw:rounded-[14px] tw:font-extrabold tw:bg-[#ffe4e6] tw:text-[#be123c]">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="tw:grid tw:[grid-template-columns:1fr_2.4fr] tw:gap-[18px] tw:[align-items:start] tw:mb-[18px] tw:max-[1301px]:[grid-template-columns:1fr]">
        <div class="tw:[&_h1]:mt-0 tw:[&_h1]:mr-0 tw:[&_h1]:mb-2 tw:[&_h1]:ml-0 tw:[&_h1]:text-[26px] tw:[&_h1]:font-[950] tw:[&_h1]:tracking-[-0.035em] tw:[&_p]:m-0 tw:[&_p]:text-[#64748b] tw:[&_p]:text-[14px]">
            <h1>{{ ($scope ?? 'all') === 'domestic' ? 'Phải trả nhà cung cấp trong nước' : ((($scope ?? 'all') === 'import') ? 'Phải trả hàng nhập khẩu' : 'Công nợ nhà cung cấp') }}</h1>
            <p>
                @if(($scope ?? 'all') === 'domestic')
                    Phiếu nhập kho đã ghi sổ tự sinh công nợ tại đây. Dữ liệu cũ chưa phân loại vẫn được hiển thị để kế toán rà soát.
                @elseif(($scope ?? 'all') === 'import')
                    Công nợ nhà cung cấp nhập khẩu dùng chung luồng đợt thanh toán → ĐNTT → đã chi; hiện chưa có module nhập khẩu nguồn riêng nên các dòng cũ cần phân loại là “NCC hàng nhập khẩu”.
                @else
                    Bảng công nợ nhà cung cấp, đợt thanh toán và ĐNTT liên kết.
                @endif
            </p>
        </div>

        <form method="GET" action="{{ route('finance.supplier-debts.index') }}" x-data="{ ky: '{{ $period ?? 'all' }}' }" class="tw:grid tw:[grid-template-columns:160px_160px_1fr_185px_120px] tw:gap-[10px] tw:max-[1301px]:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:max-[769px]:[grid-template-columns:1fr]">
            @if(in_array(($scope ?? 'all'), ['domestic','import'], true))<input type="hidden" name="scope" value="{{ $scope }}">@endif
            <select name="period" x-model="ky" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" onchange="toggleDebtMonth(this.value)" aria-label="Kỳ lọc">
                <option value="all" {{ $period === 'all' ? 'selected' : '' }}>Toàn thời gian</option>
                <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Theo tháng</option>
            </select>

            <input id="debtMonthFilter" type="month" name="month" x-bind:disabled="ky === 'all'" x-bind:style="ky === 'all' ? 'opacity:.45' : 'opacity:1'" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" value="{{ $month }}" aria-label="Tháng công nợ">

            <input type="text" name="keyword" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" value="{{ $keyword }}" placeholder="Tìm NCC, chứng từ, ghi chú..." aria-label="Từ khoá tìm kiếm">

            <select name="status" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" aria-label="Trạng thái">
                <option value="" {{ $status === '' ? 'selected' : '' }}>Tất cả trạng thái</option>
                <option value="unpaid" {{ $status === 'unpaid' ? 'selected' : '' }}>Chưa thanh toán</option>
                <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Đang thanh toán</option>
                <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Đã thanh toán</option>
            </select>

            <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:[border:0] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#2563eb,#1d4ed8)] tw:shadow-[0_16px_34px_rgba(37,99,235,0.22)]" type="submit">Lọc</button>
        </form>
    </div>

    <div class="tw:grid tw:[grid-template-columns:repeat(4,minmax(0,1fr))] tw:gap-4 tw:my-[18px] tw:mx-0 tw:max-[1301px]:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:max-[769px]:[grid-template-columns:1fr]">
        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-5 tw:flex tw:gap-[15px] tw:items-center tw:shadow-[0_20px_48px_rgba(15,23,42,0.06)] tw:[&_small]:block tw:[&_small]:text-[#64748b] tw:[&_small]:mt-[5px] tw:[&_[data-sd-kpi-icon]]:bg-[#eaf2ff] tw:[&_[data-sd-kpi-icon]]:text-[#2563eb]">
            <div data-sd-kpi-icon class="tw:w-14 tw:h-14 tw:rounded-[18px] tw:grid tw:place-items-center tw:text-[24px] tw:shrink-0 tw:grow-0 tw:basis-auto">▣</div>
            <div>
                <div class="tw:text-[#475569] tw:text-[13px] tw:font-[900] tw:mb-[6px]">Tổng phải trả</div>
                <div class="tw:text-[22px] tw:font-[950] tw:tracking-[-0.035em]">{{ $fmt->money($summary['total_amount'] ?? 0) }}</div>
                <small>{{ $summary['debt_count'] ?? 0 }} công nợ</small>
            </div>
        </div>

        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-5 tw:flex tw:gap-[15px] tw:items-center tw:shadow-[0_20px_48px_rgba(15,23,42,0.06)] tw:[&_small]:block tw:[&_small]:text-[#64748b] tw:[&_small]:mt-[5px] tw:[&_[data-sd-kpi-icon]]:bg-[#dcfce7] tw:[&_[data-sd-kpi-icon]]:text-[#059669]">
            <div data-sd-kpi-icon class="tw:w-14 tw:h-14 tw:rounded-[18px] tw:grid tw:place-items-center tw:text-[24px] tw:shrink-0 tw:grow-0 tw:basis-auto">✓</div>
            <div>
                <div class="tw:text-[#475569] tw:text-[13px] tw:font-[900] tw:mb-[6px]">Đã thanh toán</div>
                <div class="tw:text-[22px] tw:font-[950] tw:tracking-[-0.035em]" style="color:#059669">{{ $fmt->money($summary['paid_amount'] ?? 0) }}</div>
                <small>Đã duyệt / đã chi</small>
            </div>
        </div>

        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-5 tw:flex tw:gap-[15px] tw:items-center tw:shadow-[0_20px_48px_rgba(15,23,42,0.06)] tw:[&_small]:block tw:[&_small]:text-[#64748b] tw:[&_small]:mt-[5px] tw:[&_[data-sd-kpi-icon]]:bg-[#fef3c7] tw:[&_[data-sd-kpi-icon]]:text-[#f59e0b]">
            <div data-sd-kpi-icon class="tw:w-14 tw:h-14 tw:rounded-[18px] tw:grid tw:place-items-center tw:text-[24px] tw:shrink-0 tw:grow-0 tw:basis-auto">⌛</div>
            <div>
                <div class="tw:text-[#475569] tw:text-[13px] tw:font-[900] tw:mb-[6px]">Đang chờ thanh toán</div>
                <div class="tw:text-[22px] tw:font-[950] tw:tracking-[-0.035em]" style="color:#f59e0b">{{ $fmt->money($summary['pending_payment_amount'] ?? 0) }}</div>
                <small>{{ $summary['payment_round_count'] ?? 0 }} đợt</small>
            </div>
        </div>

        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:p-5 tw:flex tw:gap-[15px] tw:items-center tw:shadow-[0_20px_48px_rgba(15,23,42,0.06)] tw:[&_small]:block tw:[&_small]:text-[#64748b] tw:[&_small]:mt-[5px] tw:[&_[data-sd-kpi-icon]]:bg-[#ffe4e6] tw:[&_[data-sd-kpi-icon]]:text-[#e11d48]">
            <div data-sd-kpi-icon class="tw:w-14 tw:h-14 tw:rounded-[18px] tw:grid tw:place-items-center tw:text-[24px] tw:shrink-0 tw:grow-0 tw:basis-auto">!</div>
            <div>
                <div class="tw:text-[#475569] tw:text-[13px] tw:font-[900] tw:mb-[6px]">Còn phải trả</div>
                <div class="tw:text-[22px] tw:font-[950] tw:tracking-[-0.035em]" style="color:#e11d48">{{ $fmt->money($summary['remain_amount'] ?? 0) }}</div>
                <small>{{ $summary['supplier_count'] ?? 0 }} nhà cung cấp</small>
            </div>
        </div>
    </div>

    <details class="tw:mb-4 tw:py-[14px] tw:px-4 tw:border tw:[border-style:dashed] tw:border-[#bfdbfe] tw:rounded-[18px] tw:bg-[#ffffff] tw:[&_summary]:cursor-pointer tw:[&_summary]:text-[#1d4ed8] tw:[&_summary]:font-[950]">
        <summary>+ Thêm công nợ nhà cung cấp</summary>

        <form method="POST" action="{{ route('finance.supplier-debts.store') }}" enctype="multipart/form-data" class="tw:grid tw:[grid-template-columns:repeat(4,minmax(0,1fr))] tw:gap-[11px] tw:mt-[14px] tw:max-[769px]:[grid-template-columns:1fr]" x-data x-on:submit="$el.querySelectorAll('[data-money-input]').forEach(o => o.value = $tien.chuan(o.value))">
            @csrf

            @if(in_array(($scope ?? 'all'), ['domestic','import'], true))
                <input type="hidden" name="supplier_scope" value="{{ $scope }}" aria-label="Nhóm nhà cung cấp">
            @else
                <select class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="supplier_scope" aria-label="Nhóm nhà cung cấp">
                    <option value="general">Công nợ NCC khác</option>
                    <option value="domestic">NCC trong nước</option>
                    <option value="import">NCC hàng nhập khẩu</option>
                </select>
            @endif

            <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="supplier_name" placeholder="Tên nhà cung cấp" required aria-label="Tên nhà cung cấp">

            <select class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="company_name" required aria-label="Công ty">
                {{-- `<select required class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">` phải có option ĐẦU value rỗng: không thì option đầu luôn
                     được chọn sẵn, `required` vô nghĩa và người dùng dễ gửi nhầm công ty mặc định. --}}
                <option value="">— Chọn công ty —</option>
                @foreach($companyOptions as $company)
                    <option value="{{ $company }}">{{ $company }}</option>
                @endforeach
            </select>

            <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="document_no" placeholder="Số chứng từ" aria-label="Số chứng từ">
            <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="document_date" type="date" title="Ngày chứng từ" aria-label="Ngày chứng từ">
            <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="due_date" type="date" title="Hạn thanh toán" aria-label="Hạn thanh toán">
            <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="debt_month" type="month" value="{{ $month }}" required aria-label="Tháng công nợ">
            <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="total_amount" type="text" inputmode="decimal" data-money-input placeholder="Tổng tiền" required aria-label="Tổng tiền">
<textarea class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-auto tw:min-h-[76px] tw:pt-3 tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px] tw:[grid-column:span_2] tw:max-[769px]:[grid-column:span_1]" name="bank_info" placeholder="Thông tin ngân hàng" aria-label="Thông tin ngân hàng"></textarea>
            <textarea class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-auto tw:min-h-[76px] tw:pt-3 tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px] tw:[grid-column:span_2] tw:max-[769px]:[grid-column:span_1]" name="note" placeholder="Ghi chú" aria-label="Ghi chú"></textarea>

            <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:[border:0] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#2563eb,#1d4ed8)] tw:shadow-[0_16px_34px_rgba(37,99,235,0.22)]" type="submit">Lưu công nợ</button>
        </form>
    </details>

    <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px] tw:shadow-[0_24px_56px_rgba(15,23,42,0.07)] tw:overflow-hidden">
        <div class="tw:py-5 tw:px-[22px] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#e5edf7] tw:flex tw:justify-between tw:items-center tw:gap-[14px] tw:[&_h2]:mt-0 tw:[&_h2]:mr-0 tw:[&_h2]:mb-1 tw:[&_h2]:ml-0 tw:[&_h2]:text-[20px] tw:[&_h2]:font-[950] tw:[&_h2]:tracking-[-0.025em] tw:[&_p]:m-0 tw:[&_p]:text-[#64748b] tw:[&_p]:text-[13px]">
            <div>
                <h2>Danh sách công nợ</h2>
                <p>Mặc định là toàn thời gian. Bấm mắt để xem chi tiết, bấm bút để sửa, bấm × để xóa.</p>
            </div>

            <a class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" href="{{ route('finance.supplier-debts.index', in_array(($scope ?? 'all'), ['domestic','import'], true) ? ['scope' => $scope] : []) }}">Reset lọc</a>
        </div>

        <div class="tw:overflow-x-auto">
            <table class="tw:w-full tw:[border-collapse:collapse] tw:min-w-[1120px] tw:[&>thead>tr>th]:py-[15px] tw:[&>thead>tr>th]:px-[18px] tw:[&>thead>tr>th]:text-left tw:[&>thead>tr>th]:bg-[#fbfdff] tw:[&>thead>tr>th]:text-[#475569] tw:[&>thead>tr>th]:text-[12px] tw:[&>thead>tr>th]:font-[950] tw:[&>thead>tr>th]:border-b tw:[&>thead>tr>th]:[border-bottom-style:solid] tw:[&>thead>tr>th]:border-b-[#e5edf7] tw:[&>thead>tr>th]:whitespace-nowrap tw:[&>tbody>tr>td]:py-[15px] tw:[&>tbody>tr>td]:px-[18px] tw:[&>tbody>tr>td]:border-b tw:[&>tbody>tr>td]:[border-bottom-style:solid] tw:[&>tbody>tr>td]:border-b-[#e5edf7] tw:[&>tbody>tr>td]:text-[14px] tw:[&>tbody>tr>td]:align-middle tw:[&>tbody>tr:hover>td]:bg-[#fbfdff]">
                <thead>
                    <tr>
                        <th>Nhà cung cấp</th>
                        <th>Tổng phải trả</th>
                        <th>Đã thanh toán</th>
                        <th>Đang chờ</th>
                        <th>Còn phải trả</th>
                        <th>Trạng thái</th>
                        <th style="text-align:right">Hành động</th>
                    </tr>
                </thead>

                <tbody x-data="hangChiTiet()">
                    @forelse($debts as $item)

                        <tr>
                            <td>
                                <div class="tw:flex tw:items-center tw:gap-[13px] tw:min-w-[260px]">
                                    <div class="tw:w-[38px] tw:h-[38px] tw:rounded-[13px] tw:grid tw:place-items-center tw:text-[#2563eb] tw:bg-[#eaf2ff] tw:font-[950] tw:shrink-0 tw:grow-0 tw:basis-auto">{{ mb_substr($item->supplier_name ?? 'NCC', 0, 2, 'UTF-8') }}</div>
                                    <div>
                                        <div class="tw:font-[950] tw:text-[#0f172a] tw:mb-[3px]">{{ $item->supplier_name }}</div>
                                        <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]">
                                            {{ $item->document_no ?: 'Chưa có chứng từ' }}
                                            @if(!empty($item->company_name)) · {{ $item->company_name }} @endif
                                            @if(!empty($item->document_date)) · {{ date('d/m/Y', strtotime($item->document_date)) }} @endif
                                            @if(!empty($item->due_date)) · Hạn {{ date('d/m/Y', strtotime($item->due_date)) }} @endif
                                            @if(($item->source_type ?? null) === 'product_goods_receipt')
                                                · <span style="font-weight:800;color:#0284c7">Tự đồng bộ từ nhập kho {{ $item->source_code ?? '' }}</span>
                                            @endif
                                            @if(($item->supplier_scope ?? 'general') === 'general')
                                                · <span style="font-weight:800;color:#a16207">Chưa phân loại NCC</span>
                                            @elseif(($item->supplier_scope ?? '') === 'import')
                                                · <span style="font-weight:800;color:#7c3aed">Hàng nhập khẩu</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td><span class="tw:font-[950] tw:whitespace-nowrap">{{ $fmt->money($item->total_amount ?? 0) }}</span></td>
                            <td><span class="tw:font-[950] tw:whitespace-nowrap tw:text-[#059669]">{{ $fmt->money($item->paid_amount ?? 0) }}</span></td>
                            <td><span class="tw:font-[950] tw:whitespace-nowrap tw:text-[#f59e0b]">{{ $fmt->money($item->pending_payment_amount ?? 0) }}</span></td>
                            <td><span class="tw:font-[950] tw:whitespace-nowrap tw:text-[#e11d48]">{{ $fmt->money($item->remain_amount ?? 0) }}</span></td>
                            <td><span class="tw:inline-flex tw:items-center tw:h-7 tw:py-0 tw:px-[10px] tw:rounded-[999px] tw:text-[12px] tw:font-[900] tw:whitespace-nowrap {{ $item->status_class ?? 'tw:bg-[#f1f5f9] tw:text-[#475569]' }}">{{ $item->status_text ?? 'Chưa thanh toán' }}</span></td>
                            <td>
                                <div class="tw:flex tw:justify-end tw:items-center tw:flex-wrap tw:gap-2">
                                    <button type="button" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:w-9 tw:h-9 tw:rounded-[11px] tw:border tw:border-solid tw:border-[#dbe7f5] tw:bg-[#ffffff] tw:text-[#2563eb] tw:inline-grid tw:place-items-center tw:cursor-pointer tw:no-underline tw:font-[950] tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed" x-on:click="bat({{ $item->id }})" data-detail-trigger title="Xem chi tiết">&#128065;</button>
                                    <button type="button" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:w-9 tw:h-9 tw:rounded-[11px] tw:border tw:border-solid tw:border-[#dbe7f5] tw:bg-[#ffffff] tw:text-[#2563eb] tw:inline-grid tw:place-items-center tw:cursor-pointer tw:no-underline tw:font-[950] tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed" x-on:click="moVaSua({{ $item->id }})" data-edit-trigger title="{{ $item->synced_from_receipt ? 'Dữ liệu gốc lấy từ phiếu nhập kho' : 'Sửa công nợ' }}" {{ $item->synced_from_receipt ? 'disabled' : '' }}>&#9998;</button>

                                    <form method="POST" action="{{ route('finance.supplier-debts.destroy', $item->id) }}" onsubmit="return confirm('Xóa công nợ này?')" style="margin:0">
                                        @csrf
                                        @method('DELETE')
                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:w-9 tw:h-9 tw:rounded-[11px] tw:border tw:border-solid tw:border-[#dbe7f5] tw:inline-grid tw:place-items-center tw:cursor-pointer tw:no-underline tw:font-[950] tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#be123c] tw:border-[#fecdd3] tw:bg-[#fff1f2]" type="submit" title="{{ $item->synced_from_receipt ? 'Công nợ tự đồng bộ từ phiếu nhập kho, không xóa tại tài chính' : (($item->has_linked_payment_request && !$financeCompletedEditor) ? 'Đã có ĐNTT, không xóa trực tiếp' : 'Xóa công nợ') }}" {{ ($item->synced_from_receipt || ($item->has_linked_payment_request && !$financeCompletedEditor)) ? 'disabled' : '' }}>&times;</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <tr id="sd-detail-{{ $item->id }}" x-show="mo[{{ $item->id }}]" x-cloak>
                            <td colspan="7">
                                <div class="tw:p-4 tw:bg-[#f8fbff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[18px]">
                                    <div class="tw:grid tw:[grid-template-columns:.9fr_1.35fr] tw:gap-4 tw:max-[1301px]:[grid-template-columns:1fr]">
                                        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-2xl tw:p-[15px]">
                                            <div class="tw:font-[950] tw:mb-[10px]">Sửa công nợ</div>

                                            @if($item->synced_from_receipt)
                                                <div class="tw:mb-[14px] tw:py-3 tw:px-[14px] tw:rounded-[14px] tw:font-extrabold" style="margin:8px 0;background:#eff6ff;border-color:#bae6fd;color:#075985">Thông tin gốc của công nợ này tự đồng bộ từ phiếu nhập kho <b>{{ $item->source_code ?? ('#'.$item->source_id) }}</b>. Muốn đổi NCC/chứng từ/tổng tiền/hạn thanh toán, hãy sửa tại nghiệp vụ nhập kho. Kế toán vẫn tạo đợt thanh toán và ĐNTT tại đây.</div>
                                            @endif

                                            <form method="POST" action="{{ route('finance.supplier-debts.update', $item->id) }}" enctype="multipart/form-data" data-edit-grid class="tw:grid tw:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:gap-[9px] tw:max-[769px]:[grid-template-columns:1fr]" @if($item->synced_from_receipt) style="display:none" @endif x-data x-on:submit="$el.querySelectorAll('[data-money-input]').forEach(o => o.value = $tien.chuan(o.value))">
                                                @csrf
                                                @method('PUT')
                                                @if(in_array(($scope ?? 'all'), ['domestic','import'], true))
                                                    <input type="hidden" name="supplier_scope" value="{{ $scope }}" aria-label="Nhóm nhà cung cấp">
                                                @else
                                                    <select class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="supplier_scope" aria-label="Nhóm nhà cung cấp">
                                                        <option value="general" {{ ($item->supplier_scope ?? 'general') === 'general' ? 'selected' : '' }}>Công nợ NCC khác</option>
                                                        <option value="domestic" {{ ($item->supplier_scope ?? '') === 'domestic' ? 'selected' : '' }}>NCC trong nước</option>
                                                        <option value="import" {{ ($item->supplier_scope ?? '') === 'import' ? 'selected' : '' }}>NCC hàng nhập khẩu</option>
                                                    </select>
                                                @endif

                                                <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="supplier_name" value="{{ $item->supplier_name }}" required aria-label="Tên nhà cung cấp">

                                                <select class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="company_name" required aria-label="Công ty">
                                                    <option value="">— Chọn công ty —</option>
                                                    @foreach($companyOptions as $company)
                                                        <option value="{{ $company }}" {{ ($item->company_name ?? '') === $company ? 'selected' : '' }}>{{ $company }}</option>
                                                    @endforeach
                                                </select>

                                                <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="document_no" value="{{ $item->document_no }}" placeholder="Số chứng từ" aria-label="Số chứng từ">
                                                <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="document_date" type="date" value="{{ $item->document_date }}" aria-label="Ngày chứng từ">
                                                <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="due_date" type="date" value="{{ $item->due_date ?? '' }}" title="Hạn thanh toán" aria-label="Hạn thanh toán">
                                                <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="debt_month" type="month" value="{{ !empty($item->debt_month) ? date('Y-m', strtotime($item->debt_month)) : $month }}" required aria-label="Tháng công nợ">
                                                <input class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px]" name="total_amount" type="text" inputmode="decimal" data-money-input value="{{ $fmt->moneyInput($item->total_amount ?? 0) }}" required aria-label="Tổng tiền">

                                                <textarea class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-auto tw:min-h-[76px] tw:pt-3 tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px] tw:[grid-column:span_2] tw:max-[769px]:[grid-column:span_1]" name="bank_info" placeholder="Thông tin ngân hàng" aria-label="Thông tin ngân hàng">{{ $item->bank_info ?? '' }}</textarea>
                                                <textarea class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-auto tw:min-h-[76px] tw:pt-3 tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:py-0 tw:px-[14px] tw:bg-[#ffffff] tw:text-[#1e293b] tw:outline-none tw:shadow-[0_10px_24px_rgba(15,23,42,0.04)] tw:text-[14px] tw:[grid-column:span_2] tw:max-[769px]:[grid-column:span_1]" name="note" placeholder="Ghi chú" aria-label="Ghi chú">{{ $item->note ?? '' }}</textarea>

                                                <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:[border:0] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#2563eb,#1d4ed8)] tw:shadow-[0_16px_34px_rgba(37,99,235,0.22)]" type="submit">Cập nhật</button>
                                            </form>

                                            @includeIf('finance.supplier-debts._debt_files_manager', ['item' => $item])


                                            <form method="POST" action="{{ route('finance.supplier-debts.destroy', $item->id) }}" onsubmit="return confirm('Xóa công nợ này?')" style="margin-top:10px">
                                                @csrf
                                                @method('DELETE')
                                                <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#be123c] tw:bg-[#fff1f2] tw:border tw:border-solid tw:border-[#fecdd3]" type="submit" {{ ($item->synced_from_receipt || ($item->has_linked_payment_request && !$financeCompletedEditor)) ? 'disabled' : '' }}>Xóa công nợ</button>
                                            </form>

                                            @if($item->synced_from_receipt)
                                                <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]" style="margin-top:6px;color:#0369a1;font-weight:800">Công nợ nguồn nhập kho không xóa trực tiếp tại Tài chính.</div>
                                            @elseif($item->has_linked_payment_request)
                                                <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]" style="margin-top:6px;color:#be123c;font-weight:800">Công nợ đã có ĐNTT liên kết nên không xóa trực tiếp.</div>
                                            @endif

                                            <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]" style="margin-top:12px">
                                                <b>Ghi chú:</b> {!! nl2br(e($item->note ?? '—')) !!}<br>
                                                <b>Ngân hàng:</b> {!! nl2br(e($item->bank_info ?? '—')) !!}
                                            </div>
                                        </div>

                                        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-2xl tw:p-[15px]">
                                            <div class="tw:font-[950] tw:mb-[10px]">Đợt thanh toán</div>

                                            <table class="tw:w-full tw:[border-collapse:collapse] tw:[&_:is(th,td)]:py-[9px] tw:[&_:is(th,td)]:px-[10px] tw:[&_:is(th,td)]:border-b tw:[&_:is(th,td)]:[border-bottom-style:solid] tw:[&_:is(th,td)]:border-b-[#eef2f7] tw:[&_:is(th,td)]:text-[13px] tw:[&_:is(th,td)]:align-top tw:[&_th]:text-[#475569] tw:[&_th]:font-[950] tw:[&_th]:text-left tw:[&_th]:bg-[#fbfdff] tw:[&_th]:whitespace-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th>Đợt</th>
                                                        <th>Số tiền</th>
                                                        <th>Ngày</th>
                                                        <th>Trạng thái</th>
                                                        <th>ĐNTT</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>

                                                <tbody x-data="hangChiTiet()">
                                                    @forelse(($item->payment_rounds ?? collect()) as $round)

                                                        <tr>
                                                            <td>Đợt {{ $round->payment_round }}</td>
                                                            <td><b>{{ $fmt->money($round->amount ?? 0) }}</b></td>
                                                            <td>{{ !empty($round->payment_date) ? date('d/m/Y', strtotime($round->payment_date)) : '—' }}</td>
                                                            <td><span class="tw:inline-flex tw:items-center tw:h-7 tw:py-0 tw:px-[10px] tw:rounded-[999px] tw:text-[12px] tw:font-[900] tw:whitespace-nowrap {{ $round->badge_class }}">{{ $round->status_text }}</span></td>
                                                            <td>
                                                                @if($round->has_payment_request && empty($round->payment_request_missing))
                                                                    <a class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" href="{{ route('payment_requests.show', $round->payment_request_id) }}" style="height:34px;padding:0 10px;text-decoration:none" title="Mở đề nghị thanh toán #{{ $round->payment_request_id }}">Xem ĐNTT</a>
                                                                    @if($round->is_paid)
                                                                        <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]" style="color:#059669;font-weight:800">Kế toán đã chi</div>

                                                                        @if($round->is_partial_paid && $round->remaining_amount_by_request > 0)
                                                                            <div class="tw:mt-[7px] tw:py-2 tw:px-[9px] tw:border tw:border-solid tw:border-[#fed7aa] tw:rounded-xl tw:[background:linear-gradient(180deg,#fff7ed,#fff)] tw:text-[#9a3412] tw:[&_.line]:flex tw:[&_.line]:justify-between tw:[&_.line]:gap-3 tw:[&_.line]:min-w-[198px] tw:[&_b]:text-[#be123c] tw:[&_.hint]:mt-[5px] tw:[&_.hint]:pt-[5px] tw:[&_.hint]:border-t tw:[&_.hint]:[border-top-style:dashed] tw:[&_.hint]:border-t-[#fdba74] tw:[&_.hint]:text-[#64748b] tw:[&_.hint]:text-[10px] tw:[&_.hint]:font-bold tw:[&_form]:mt-[7px] tw:[&_form]:mr-0 tw:[&_form]:mb-0 tw:[&_form]:ml-0">
                                                                                <div class="line"><span>Đợt cần TT</span><b>{{ $fmt->money($round->amount ?? 0) }}</b></div>
                                                                                <div class="line"><span>Đã chi ĐNTT</span><b>{{ $fmt->money($round->paid_amount_by_request) }}</b></div>
                                                                                <div class="line"><span>Còn thiếu</span><b>{{ $fmt->money($round->remaining_amount_by_request) }}</b></div>
                                                                                <div class="hint">Chỉ tạo thêm 1 đợt công nợ cho phần còn thiếu, không tự link ĐNTT khác.</div>

                                                                                @if($financeCompletedEditor && !$round->remaining_round_exists)
                                                                                    <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.create-remaining-round', $round->id) }}">
                                                                                        @csrf
                                                                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[31px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-[10px] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:border tw:border-solid tw:border-[#fed7aa] tw:text-[#c2410c] tw:bg-[#ffffff] tw:text-[11px] tw:font-[900] tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed" type="submit">+ Tạo đợt còn lại {{ $fmt->money($round->remaining_amount_by_request) }}</button>
                                                                                    </form>
                                                                                @elseif($round->remaining_round_exists)
                                                                                    <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]" style="margin-top:6px;color:#059669;font-weight:800">Đã có dòng đợt còn lại.</div>
                                                                                @endif
                                                                            </div>
                                                                        @endif
                                                                    @elseif($round->is_locked)
                                                                        <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]">Đã gửi/duyệt, khóa sửa trực tiếp</div>
                                                                    @endif
                                                                @elseif($round->is_paid)
                                                                    <span class="tw:inline-flex tw:items-center tw:h-7 tw:py-0 tw:px-[10px] tw:rounded-[999px] tw:text-[12px] tw:font-[900] tw:whitespace-nowrap tw:bg-[#dcfce7] tw:text-[#047857]">Đã thanh toán</span>
                                                                    <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]">Không tạo ĐNTT nữa</div>
                                                                @else
                                                                    <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.create-payment-request', $round->id) }}">
                                                                        @csrf
                                                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" type="submit" style="height:34px;padding:0 10px">Tạo ĐNTT</button>
                                                                    </form>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <div class="tw:flex tw:gap-[6px] tw:justify-end tw:items-center tw:flex-wrap">
                                                                    <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:w-9 tw:h-9 tw:rounded-[11px] tw:border tw:border-solid tw:border-[#dbe7f5] tw:bg-[#ffffff] tw:text-[#2563eb] tw:inline-grid tw:place-items-center tw:cursor-pointer tw:no-underline tw:font-[950] tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed" type="button" title="{{ ($round->is_paid && !$financeCompletedEditor) ? 'Đợt đã thanh toán, không sửa trực tiếp' : 'Sửa đợt' }}" x-on:click="bat({{ $round->id }})" data-round-trigger {{ (($round->is_locked || $round->is_paid) && !$financeCompletedEditor) ? 'disabled' : '' }}>✎</button>

                                                                    @if(empty($round->payment_request_id) || $financeCompletedEditor)
                                                                        <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.destroy', $round->id) }}" onsubmit="return confirm('Xóa đợt thanh toán này? Nếu có ĐNTT liên kết, phiếu ĐNTT không bị xóa, chỉ gỡ liên kết dòng công nợ.')">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:w-9 tw:h-9 tw:rounded-[11px] tw:border tw:border-solid tw:border-[#dbe7f5] tw:inline-grid tw:place-items-center tw:cursor-pointer tw:no-underline tw:font-[950] tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#be123c] tw:border-[#fecdd3] tw:bg-[#fff1f2]" type="submit" title="Xóa">×</button>
                                                                        </form>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>

                                                        <tr id="sd-round-edit-{{ $round->id }}" x-show="mo[{{ $round->id }}]" x-cloak>
                                                            <td colspan="6">
                                                                <div class="tw:p-3 tw:border tw:[border-style:dashed] tw:border-[#bfdbfe] tw:rounded-[14px] tw:bg-[#f8fbff]">
                                                                    <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.update', $round->id) }}" enctype="multipart/form-data" class="tw:grid tw:gap-2 tw:[align-items:start] tw:[grid-template-columns:70px_82px_145px_135px_130px_minmax(130px,1fr)_minmax(125px,1fr)_auto_auto] tw:max-[769px]:[grid-template-columns:1fr] tw:[&_:is(input,select,textarea)]:h-10 tw:[&_:is(input,select,textarea)]:border tw:[&_:is(input,select,textarea)]:border-solid tw:[&_:is(input,select,textarea)]:border-[#e5edf7] tw:[&_:is(input,select,textarea)]:rounded-[11px] tw:[&_:is(input,select,textarea)]:py-0 tw:[&_:is(input,select,textarea)]:px-[10px] tw:[&_:is(input,select,textarea)]:min-w-0 tw:[&_:is(input,select,textarea)]:bg-[#ffffff] tw:[&_textarea]:pt-[10px] tw:[&_textarea]:resize-y" x-data="dotThanhToan({{ (float) ($item->total_amount ?? 0) }})" x-on:submit="chuanHoaTruocKhiGui($el)">
                                                                        @csrf
                                                                        @method('PUT')

                                                                        <input name="payment_round" type="number" min="1" value="{{ $round->payment_round }}" placeholder="Đợt" required aria-label="Số đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                        <input name="_percent" type="text" inputmode="decimal" data-round-percent x-model="phanTram" x-on:input="theoPhanTram()" value="{{ $round->percent_text }}" placeholder="%" title="Phần trăm theo tổng công nợ" aria-label="Phần trăm của tổng công nợ" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                        <input name="amount" type="text" inputmode="decimal" data-money-input data-round-amount x-model="soTien" x-on:input="theoSoTien()" value="{{ $fmt->moneyInput($round->amount ?? 0) }}" placeholder="Số tiền" required aria-label="Số tiền đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                        <input name="payment_date" type="date" value="{{ $round->payment_date ?? '' }}" aria-label="Ngày thanh toán" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">

                                                                        <select name="status" aria-label="Trạng thái" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                            <option value="planned" {{ $round->status_key === 'planned' ? 'selected' : '' }}>Dự kiến</option>
                                                                            <option value="paid" {{ $round->status_key === 'paid' ? 'selected' : '' }}>Đã thanh toán</option>
                                                                            <option value="requested" {{ $round->status_key === 'requested' ? 'selected' : '' }}>Đã lập ĐNTT</option>
                                                                        </select>

                                                                        <textarea name="note" placeholder="Ghi chú" aria-label="Ghi chú" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">{{ $round->note ?? '' }}</textarea>
                                                                        <input name="attachments[]" type="file" multiple aria-label="Tệp đính kèm" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">

                                                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:[border:0] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#2563eb,#1d4ed8)] tw:shadow-[0_16px_34px_rgba(37,99,235,0.22)]" type="submit" style="height:40px">Lưu</button>
                                                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" type="button" style="height:40px" x-on:click="bat({{ $round->id }})">Đóng</button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6">Chưa có đợt thanh toán.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>

                                            <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.store', $item->id) }}" enctype="multipart/form-data" class="tw:grid tw:gap-2 tw:mt-3 tw:[align-items:start] tw:[grid-template-columns:68px_74px_minmax(110px,1fr)_130px_118px_minmax(120px,1fr)_minmax(100px,1fr)_auto] tw:max-[1001px]:[grid-template-columns:1fr] tw:[&_:is(input,select,textarea)]:h-10 tw:[&_:is(input,select,textarea)]:border tw:[&_:is(input,select,textarea)]:border-solid tw:[&_:is(input,select,textarea)]:border-[#e5edf7] tw:[&_:is(input,select,textarea)]:rounded-[11px] tw:[&_:is(input,select,textarea)]:py-0 tw:[&_:is(input,select,textarea)]:px-[10px] tw:[&_:is(input,select,textarea)]:min-w-0 tw:[&_:is(input,select,textarea)]:bg-[#ffffff] tw:[&_textarea]:pt-[10px] tw:[&_textarea]:resize-y" x-data="dotThanhToan({{ (float) ($item->total_amount ?? 0) }})" x-on:submit="chuanHoaTruocKhiGui($el)">
                                                @csrf

                                                <input name="payment_round" type="number" min="1" placeholder="Đợt" aria-label="Số đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                <input name="_percent" type="text" inputmode="decimal" data-round-percent x-model="phanTram" x-on:input="theoPhanTram()" placeholder="%" title="Phần trăm theo tổng công nợ" aria-label="Phần trăm của tổng công nợ" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                <input name="amount" type="text" inputmode="decimal" data-money-input data-round-amount x-model="soTien" x-on:input="theoSoTien()" placeholder="Số tiền" required aria-label="Số tiền đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                <input name="payment_date" type="date" aria-label="Ngày thanh toán" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">

                                                <select name="status" aria-label="Trạng thái" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                    <option value="planned">Dự kiến</option>
                                                    <option value="paid">Đã thanh toán</option>
                                                </select>

                                                <textarea name="note" placeholder="Ghi chú" aria-label="Ghi chú" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]"></textarea>
                                                <input name="attachments[]" type="file" multiple aria-label="Tệp đính kèm" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">

                                                <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:[border:0] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#2563eb,#1d4ed8)] tw:shadow-[0_16px_34px_rgba(37,99,235,0.22)]" type="submit" style="height:40px">+ Đợt</button>
                                            </form>



                                            <div x-data data-split-trigger class="tw:flex tw:justify-end tw:mt-[10px]">
                                                <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" type="button" style="height:40px" data-split-open
                                                        x-on:click="$dispatch('mo-chia-dot-{{ $item->id }}')">+ Thêm dòng theo %</button>
                                            </div>
                                            {{-- Thẻ chia đợt: `x-data` ở đây, nút mở nằm NGOÀI nên dùng sự kiện
                                                 window có tên (đúng nếp x-ui.modal/disclosure) thay vì bọc thêm thẻ cha. --}}
                                            <div x-data="chiaDotCongNo({
                                                     tong: {{ (float) ($item->total_amount ?? 0) }},
                                                     daCo: {{ (float) $item->existing_round_amount }},
                                                     dotKeTiep: {{ (int) $item->next_bulk_round }}
                                                 })"
                                                 x-on:mo-chia-dot-{{ $item->id }}.window="moThe()"
                                                 x-show="mo" x-cloak
                                                 data-split-root class="tw:mt-[14px] tw:p-3 tw:border tw:[border-style:dashed] tw:border-[#bfdbfe] tw:rounded-2xl tw:bg-[#f8fbff] tw:[&_[data-split-count]]:hidden tw:[&_[data-split-equal]]:hidden">
                                                <div class="tw:flex tw:items-center tw:justify-between tw:gap-[10px] tw:flex-wrap tw:mb-[10px]">
                                                    <div>
                                                        <div class="tw:font-[950] tw:mb-[10px]" style="margin-bottom:3px">Thêm từng đợt theo %</div>
                                                        <div class="tw:text-[#64748b] tw:text-[12px] tw:leading-[1.45]">Mỗi lần bấm “Thêm dòng” sẽ thêm 1 dòng. Nhập % để tự nhảy số tiền.</div>
                                                    </div>
                                                    <div class="tw:flex tw:gap-2 tw:items-center tw:flex-wrap tw:[&_input]:w-[74px] tw:[&_input]:h-[38px] tw:[&_input]:border tw:[&_input]:border-solid tw:[&_input]:border-[#e5edf7] tw:[&_input]:rounded-[11px] tw:[&_input]:py-0 tw:[&_input]:px-[10px] tw:[&_input]:bg-[#ffffff]">
                                                        <input type="number" min="1" max="24" value="3" data-split-count title="Số đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" type="button" style="height:38px" data-split-refresh x-on:click="themDong()">Thêm dòng</button>
                                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#1d4ed8] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e5edf7]" type="button" style="height:38px" data-split-equal x-on:click="chiaDeu()">Chia đều 100%</button>
                                                    </div>
                                                </div>
                                                <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.store', $item->id) }}" class="sd-bulk-form" x-data x-on:submit="$el.querySelectorAll('[data-money-input]').forEach(o => o.value = $tien.chuan(o.value))">
                                                      class="sd-bulk-form" x-on:submit="chuanHoaTruocKhiGui($el)">
                                                    @csrf
                                                    <div class="tw:grid tw:gap-2" data-split-rows>
                                                        <template x-for="(d, i) in dong" :key="i">
                                                            <div class="tw:grid tw:[grid-template-columns:72px_90px_150px_145px_132px_minmax(150px,1fr)_38px] tw:gap-2 tw:[align-items:start] tw:max-[769px]:[grid-template-columns:1fr] tw:[&_:is(input,select,textarea)]:h-10 tw:[&_:is(input,select,textarea)]:border tw:[&_:is(input,select,textarea)]:border-solid tw:[&_:is(input,select,textarea)]:border-[#e5edf7] tw:[&_:is(input,select,textarea)]:rounded-[11px] tw:[&_:is(input,select,textarea)]:py-0 tw:[&_:is(input,select,textarea)]:px-[10px] tw:[&_:is(input,select,textarea)]:min-w-0 tw:[&_:is(input,select,textarea)]:bg-[#ffffff] tw:[&_textarea]:pt-[10px] tw:[&_textarea]:resize-y" data-split-row>
                                                                <input :name="'bulk_rounds[' + i + '][payment_round]'" type="number" min="1" x-model="d.dot" placeholder="Đợt" aria-label="Số đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                <input :name="'bulk_rounds[' + i + '][percent]'" type="text" inputmode="decimal"
                                                                       data-split-percent x-model="d.phanTram" x-on:input="theoPhanTram(d)" placeholder="%" aria-label="Phần trăm của tổng công nợ" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                <input :name="'bulk_rounds[' + i + '][amount]'" type="text" inputmode="decimal"
                                                                       data-money-input data-split-amount x-model="d.soTien" placeholder="Số tiền" aria-label="Số tiền đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                <input :name="'bulk_rounds[' + i + '][payment_date]'" type="date" x-model="d.ngay" aria-label="Ngày thanh toán" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                <select :name="'bulk_rounds[' + i + '][status]'" x-model="d.trangThai" aria-label="Trạng thái đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]">
                                                                    <option value="planned">Dự kiến</option>
                                                                    <option value="paid">Đã thanh toán</option>
                                                                    <option value="requested">Đã lập ĐNTT</option>
                                                                </select>
                                                                <textarea :name="'bulk_rounds[' + i + '][note]'" x-model="d.ghiChu" placeholder="Ghi chú" aria-label="Ghi chú đợt" class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid]"></textarea>
                                                                <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:w-9 tw:h-9 tw:rounded-[11px] tw:border tw:border-solid tw:border-[#dbe7f5] tw:inline-grid tw:place-items-center tw:cursor-pointer tw:no-underline tw:font-[950] tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:text-[#be123c] tw:border-[#fecdd3] tw:bg-[#fff1f2]" type="button" x-on:click="xoaDong(i)" title="Xóa dòng">×</button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <div class="tw:mt-[10px] tw:flex tw:justify-between tw:items-center tw:gap-[10px] tw:flex-wrap tw:text-[#475569] tw:text-[13px] tw:font-extrabold tw:[&_b]:text-[#0f172a]">
                                                        <span data-split-summary>Tổng dòng mới: <b x-text="tongTienChu">0 đ</b> / <span x-text="tongPhanTramChu">0</span>%
                                                            · Đã có đợt: <b x-text="daCoChu"></b>
                                                            · Còn lại sau lưu: <b x-text="conLaiChu"></b><template x-if="vuotTran"><span style="color:#be123c"> - VƯỢT tổng công nợ!</span></template></span>
                                                        <button class="tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-[#2563eb] tw:focus-visible:[outline-style:solid] tw:h-[46px] tw:rounded-[14px] tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:py-0 tw:px-4 tw:font-[900] tw:no-underline tw:cursor-pointer tw:whitespace-nowrap tw:disabled:opacity-[.55] tw:disabled:cursor-not-allowed tw:[border:0] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#2563eb,#1d4ed8)] tw:shadow-[0_16px_34px_rgba(37,99,235,0.22)]" type="submit" style="height:40px">Lưu các đợt</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="tw:py-11 tw:px-5 tw:text-center tw:text-[#64748b] tw:font-extrabold">Không có công nợ phù hợp bộ lọc.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($financeCompletedEditor)
<script id="EGO_FINANCE_EDITOR_UNLOCK_UI">
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('button[disabled]').forEach(function (btn) {
            const title = (btn.getAttribute('title') || '').toLowerCase();
            if (
                title.includes('thanh toán') ||
                title.includes('đntt') ||
                title.includes('khóa') ||
                title.includes('khong') ||
                title.includes('không')
            ) {
                btn.disabled = false;
                btn.title = 'Được phép sửa/xóa bởi {{ $financeFullAccessEmail }}';
            }
        });
    });
</script>
@endif

@endsection

