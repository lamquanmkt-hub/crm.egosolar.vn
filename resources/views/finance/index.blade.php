
{{-- Hai con số badge (đơn chờ duyệt, phiếu vật tư chờ duyệt) do
     App\Services\System\SidebarStatusService cấp cho partials.sidebar qua view
     composer. Trước đây đúng chỗ này có một khối 17 dòng CHÉP QUA 9 VIEW tự chạy
     lại hai câu COUNT rồi nuốt lỗi bằng catch(Throwable). Giá trị nó tính ra bị
     composer ghi đè nên không hiển thị ở đâu — chỉ tốn 2 câu truy vấn mỗi lần
     dựng trang. --}}

@extends('layouts.app')

@section('content')
<div class="tw:p-[18px] tw:bg-[#f4f7fb] tw:max-[992px]:p-[14px]">
    {{-- HERO --}}
    <section class="tw:flex tw:justify-between tw:gap-[18px] tw:py-[22px] tw:px-6 tw:rounded-[24px] tw:[background:radial-gradient(circle_at_top_right,rgba(59,130,246,.22),transparent_28%),linear-gradient(135deg,#071224_0%,#0b1730_45%,#16346e_100%)] tw:text-[#ffffff] tw:shadow-[0_18px_42px_rgba(2,6,23,0.18)] tw:mb-4 tw:max-[992px]:flex-col tw:max-[992px]:p-[18px]">
        <div class="tw:flex-auto">
            <div class="tw:inline-flex tw:items-center tw:py-[5px] tw:px-[10px] tw:rounded-[999px] tw:bg-[rgba(255,255,255,0.08)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.14)] tw:text-[#dbeafe] tw:text-[11px] tw:font-extrabold tw:tracking-[.08em] tw:uppercase tw:mb-[10px]">Executive Finance Dashboard</div>
            <h1 class="tw:m-0 tw:text-[34px] tw:leading-[1.05] tw:font-extrabold tw:tracking-[-.03em] tw:max-[992px]:text-[28px]">Tổng quan tài chính</h1>
            <p class="tw:mt-[10px] tw:mr-0 tw:mb-0 tw:ml-0 tw:text-[rgba(255,255,255,0.78)] tw:text-[14px] tw:leading-[1.6] tw:max-w-[760px]">
                Theo dõi doanh thu, giá vốn, lợi nhuận, công nợ và dòng tiền theo góc nhìn quản trị.
            </p>

            <div class="tw:grid tw:[grid-template-columns:repeat(3,minmax(0,1fr))] tw:gap-[10px] tw:mt-[18px] tw:max-[992px]:[grid-template-columns:1fr]">
                <div class="tw:py-3 tw:px-[14px] tw:rounded-[14px] tw:bg-[rgba(255,255,255,0.06)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.10)] tw:[&_span]:block tw:[&_span]:text-[rgba(255,255,255,0.66)] tw:[&_span]:text-[12px] tw:[&_span]:mb-[6px] tw:[&_strong]:text-[20px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#ffffff]">
                    <span>Tỷ lệ thu hồi</span>
                    <strong>{{ $collectionRateText }}</strong>
                </div>
                <div class="tw:py-3 tw:px-[14px] tw:rounded-[14px] tw:bg-[rgba(255,255,255,0.06)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.10)] tw:[&_span]:block tw:[&_span]:text-[rgba(255,255,255,0.66)] tw:[&_span]:text-[12px] tw:[&_span]:mb-[6px] tw:[&_strong]:text-[20px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#ffffff]">
                    <span>Đơn hàng</span>
                    <strong>{{ $totalOrdersText }}</strong>
                </div>
                <div class="tw:py-3 tw:px-[14px] tw:rounded-[14px] tw:bg-[rgba(255,255,255,0.06)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.10)] tw:[&_span]:block tw:[&_span]:text-[rgba(255,255,255,0.66)] tw:[&_span]:text-[12px] tw:[&_span]:mb-[6px] tw:[&_strong]:text-[20px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#ffffff]">
                    <span>Phiếu chờ xử lý</span>
                    <strong>{{ $pendingRequestsText }}</strong>
                </div>
            </div>
        </div>

        <div class="tw:flex tw:flex-col tw:gap-[10px] tw:min-w-[210px] tw:max-[992px]:min-w-[unset] tw:max-[992px]:w-full">
            <a href="{{ route('finance.reports') }}" class="tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:rounded-xl tw:no-underline tw:font-bold tw:[transition:.2s_ease] tw:border tw:border-solid tw:hover:[transform:translateY(-1px)] tw:text-[#ffffff] tw:bg-[rgba(255,255,255,0.08)] tw:border-[rgba(255,255,255,0.14)] tw:hover:text-[#ffffff] tw:py-[11px] tw:px-[14px] tw:text-[14px]">
                <i class="bi bi-bar-chart-line"></i>
                <span>Xem báo cáo</span>
            </a>

            <a href="{{ route('finance.payment-request') }}" class="tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:rounded-xl tw:no-underline tw:font-bold tw:[transition:.2s_ease] tw:border tw:border-solid tw:hover:[transform:translateY(-1px)] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#3b82f6,#2563eb)] tw:shadow-[0_12px_24px_rgba(37,99,235,0.22)] tw:border-transparent tw:hover:text-[#ffffff] tw:py-[11px] tw:px-[14px] tw:text-[14px]">
                <i class="bi bi-send-check"></i>
                <span>Đề nghị thanh toán</span>
            </a>
        </div>
    </section>

    {{-- FILTER --}}
    <section class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:mb-4">
        <form method="GET" action="{{ route('finance.index') }}" class="tw:flex tw:flex-col tw:gap-[14px]">
            <div class="tw:grid tw:[grid-template-columns:2fr_1fr_1fr_1fr] tw:gap-3 tw:max-[1401px]:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:max-[992px]:[grid-template-columns:1fr]">
                <div class="tw:[&_label]:block tw:[&_label]:text-[12px] tw:[&_label]:font-bold tw:[&_label]:text-[#475569] tw:[&_label]:mb-[6px] tw:[&_:is(input,select)]:w-full tw:[&_:is(input,select)]:h-[42px] tw:[&_:is(input,select)]:border tw:[&_:is(input,select)]:border-solid tw:[&_:is(input,select)]:border-[#dbe4ee] tw:[&_:is(input,select)]:rounded-xl tw:[&_:is(input,select)]:py-0 tw:[&_:is(input,select)]:px-3 tw:[&_:is(input,select)]:text-[13px] tw:[&_:is(input,select)]:text-[#0f172a] tw:[&_:is(input,select)]:bg-[#ffffff] tw:[&_:is(input,select)]:outline-none tw:[&_:is(input,select):focus]:border-[#93c5fd] tw:[&_:is(input,select):focus]:shadow-[0_0_0_3px_rgba(59,130,246,0.10)]">
                    <label>Từ khóa</label>
                    <input
                        type="text"
                        name="keyword"
                        value="{{ $filters['keyword'] ?? '' }}"
                        placeholder="Mã đơn, tên khách hàng..."
                    >
                </div>

                <div class="tw:[&_label]:block tw:[&_label]:text-[12px] tw:[&_label]:font-bold tw:[&_label]:text-[#475569] tw:[&_label]:mb-[6px] tw:[&_:is(input,select)]:w-full tw:[&_:is(input,select)]:h-[42px] tw:[&_:is(input,select)]:border tw:[&_:is(input,select)]:border-solid tw:[&_:is(input,select)]:border-[#dbe4ee] tw:[&_:is(input,select)]:rounded-xl tw:[&_:is(input,select)]:py-0 tw:[&_:is(input,select)]:px-3 tw:[&_:is(input,select)]:text-[13px] tw:[&_:is(input,select)]:text-[#0f172a] tw:[&_:is(input,select)]:bg-[#ffffff] tw:[&_:is(input,select)]:outline-none tw:[&_:is(input,select):focus]:border-[#93c5fd] tw:[&_:is(input,select):focus]:shadow-[0_0_0_3px_rgba(59,130,246,0.10)]">
                    <label>Từ ngày</label>
                    <input
                        type="date"
                        name="from_date"
                        value="{{ $filters['from_date'] ?? '' }}"
                    >
                </div>

                <div class="tw:[&_label]:block tw:[&_label]:text-[12px] tw:[&_label]:font-bold tw:[&_label]:text-[#475569] tw:[&_label]:mb-[6px] tw:[&_:is(input,select)]:w-full tw:[&_:is(input,select)]:h-[42px] tw:[&_:is(input,select)]:border tw:[&_:is(input,select)]:border-solid tw:[&_:is(input,select)]:border-[#dbe4ee] tw:[&_:is(input,select)]:rounded-xl tw:[&_:is(input,select)]:py-0 tw:[&_:is(input,select)]:px-3 tw:[&_:is(input,select)]:text-[13px] tw:[&_:is(input,select)]:text-[#0f172a] tw:[&_:is(input,select)]:bg-[#ffffff] tw:[&_:is(input,select)]:outline-none tw:[&_:is(input,select):focus]:border-[#93c5fd] tw:[&_:is(input,select):focus]:shadow-[0_0_0_3px_rgba(59,130,246,0.10)]">
                    <label>Đến ngày</label>
                    <input
                        type="date"
                        name="to_date"
                        value="{{ $filters['to_date'] ?? '' }}"
                    >
                </div>

                <div class="tw:[&_label]:block tw:[&_label]:text-[12px] tw:[&_label]:font-bold tw:[&_label]:text-[#475569] tw:[&_label]:mb-[6px] tw:[&_:is(input,select)]:w-full tw:[&_:is(input,select)]:h-[42px] tw:[&_:is(input,select)]:border tw:[&_:is(input,select)]:border-solid tw:[&_:is(input,select)]:border-[#dbe4ee] tw:[&_:is(input,select)]:rounded-xl tw:[&_:is(input,select)]:py-0 tw:[&_:is(input,select)]:px-3 tw:[&_:is(input,select)]:text-[13px] tw:[&_:is(input,select)]:text-[#0f172a] tw:[&_:is(input,select)]:bg-[#ffffff] tw:[&_:is(input,select)]:outline-none tw:[&_:is(input,select):focus]:border-[#93c5fd] tw:[&_:is(input,select):focus]:shadow-[0_0_0_3px_rgba(59,130,246,0.10)]">
                    <label>Trạng thái đơn</label>
                    <select name="order_status">
                        <option value="">-- Tất cả --</option>
                        @foreach($orderStatuses ?? [] as $status)
                            <option value="{{ $status }}" {{ (($filters['order_status'] ?? '') === $status) ? 'selected' : '' }}>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="tw:flex tw:gap-[10px] tw:flex-wrap">
                <button type="submit" class="tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:rounded-xl tw:no-underline tw:font-bold tw:[transition:.2s_ease] tw:border tw:border-solid tw:hover:[transform:translateY(-1px)] tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#3b82f6,#2563eb)] tw:shadow-[0_12px_24px_rgba(37,99,235,0.22)] tw:border-transparent tw:hover:text-[#ffffff] tw:py-[10px] tw:px-[13px] tw:text-[13px]">
                    <i class="bi bi-funnel"></i>
                    <span>Lọc dữ liệu</span>
                </button>

                <a href="{{ route('finance.index') }}" class="tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:rounded-xl tw:no-underline tw:font-bold tw:[transition:.2s_ease] tw:border tw:border-solid tw:hover:[transform:translateY(-1px)] tw:text-[#0f172a] tw:bg-[#ffffff] tw:border-[#e7edf5] tw:hover:text-[#0f172a] tw:py-[10px] tw:px-[13px] tw:text-[13px]">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Đặt lại</span>
                </a>
            </div>
        </form>
    </section>

    {{-- KHỐI 1: DOANH THU - GIÁ VỐN - LỢI NHUẬN --}}
    <section class="tw:mb-4">
        <div class="tw:flex tw:justify-between tw:items-end tw:gap-3 tw:mb-[10px] tw:[&_h2]:m-0 tw:[&_h2]:text-[20px] tw:[&_h2]:font-extrabold tw:[&_h2]:text-[#0f172a]">
            <div>
                <div class="tw:text-[11px] tw:text-[#5b6b7f] tw:font-extrabold tw:tracking-[.08em] tw:uppercase tw:mb-1">Profit Overview</div>
                <h2>Doanh thu - Giá vốn - Lợi nhuận</h2>
            </div>
        </div>

        <div class="tw:grid tw:gap-[14px] tw:[grid-template-columns:repeat(4,minmax(0,1fr))] tw:max-[1401px]:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:max-[992px]:[grid-template-columns:1fr]">
            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#2563eb]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Tổng doanh thu</span>
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em]">{{ $revenueText }}</div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Tổng giá trị bán ra từ đơn hàng</div>
            </article>

            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#d97706]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Tổng giá vốn</span>
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em]">{{ $costText }}</div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Tính theo số lượng × giá agent của sản phẩm</div>
            </article>

            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#16a34a]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Lợi nhuận gộp</span>
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em] {{ $grossProfitClass }}">
                    {{ $grossProfitText }}
                </div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Doanh thu - Giá vốn</div>
            </article>

            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#7c3aed]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Biên lợi nhuận gộp</span>
                    <i class="bi bi-percent"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em]">{{ $grossMarginText }}</div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Lợi nhuận gộp / Doanh thu</div>
            </article>
        </div>
    </section>

    {{-- KHỐI 2: CÔNG NỢ --}}
    <section class="tw:mb-4">
        <div class="tw:flex tw:justify-between tw:items-end tw:gap-3 tw:mb-[10px] tw:[&_h2]:m-0 tw:[&_h2]:text-[20px] tw:[&_h2]:font-extrabold tw:[&_h2]:text-[#0f172a]">
            <div>
                <div class="tw:text-[11px] tw:text-[#5b6b7f] tw:font-extrabold tw:tracking-[.08em] tw:uppercase tw:mb-1">Debt Overview</div>
                <h2>Công nợ phải thu</h2>
            </div>
        </div>

        <div class="tw:grid tw:gap-[14px] tw:[grid-template-columns:repeat(4,minmax(0,1fr))] tw:max-[1401px]:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:max-[992px]:[grid-template-columns:1fr]">
            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#475569]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Tổng phải thu</span>
                    <i class="bi bi-journal-text"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em]">{{ $receivableBaseText }}</div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Tổng công nợ gốc theo bảng debt</div>
            </article>

            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#16a34a]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Đã thu</span>
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em]">{{ $collectedText }}</div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Tổng tiền đã thu theo debt</div>
            </article>

            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#dc2626]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Còn nợ</span>
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em]">{{ $receivableText }}</div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Số dư nợ còn lại</div>
            </article>

            <article class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[18px] tw:p-4 tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:relative tw:overflow-hidden tw:after:content-[''] tw:after:absolute tw:after:right-[-20px] tw:after:bottom-[-20px] tw:after:w-[90px] tw:after:h-[90px] tw:after:rounded-[50%] tw:after:opacity-[.08] tw:after:bg-[#d97706]">
                <div class="tw:flex tw:justify-between tw:items-center tw:mb-3 tw:[&_i]:text-[#5b6b7f] tw:[&_i]:text-[18px]">
                    <span class="tw:text-[#475569] tw:text-[13px] tw:font-semibold">Công nợ quá hạn</span>
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="tw:text-[#0f172a] tw:text-[24px] tw:font-extrabold tw:leading-[1.15] tw:mb-2 tw:tracking-[-.02em]">{{ $overdueText }}</div>
                <div class="tw:text-[#475569] tw:text-[12px] tw:leading-[1.5]">Các khoản overdue</div>
            </article>
        </div>
    </section>

    {{-- KHỐI 3: DÒNG TIỀN --}}
    <section class="tw:grid tw:[grid-template-columns:1.05fr_.95fr] tw:gap-[14px] tw:mb-[14px] tw:max-[1401px]:[grid-template-columns:1fr]">
        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[20px] tw:p-[18px] tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)]">
            <div class="tw:flex tw:justify-between tw:items-start tw:gap-3 tw:mb-[14px] tw:[&_h2]:m-0 tw:[&_h2]:text-[20px] tw:[&_h2]:text-[#0f172a] tw:[&_h2]:font-extrabold">
                <div>
                    <div class="tw:text-[11px] tw:text-[#5b6b7f] tw:font-extrabold tw:tracking-[.08em] tw:uppercase tw:mb-1">Cashflow Snapshot</div>
                    <h2>Dòng tiền theo bộ lọc</h2>
                </div>
                <span class="tw:inline-flex tw:items-center tw:py-[7px] tw:px-[10px] tw:rounded-[999px] tw:text-[11px] tw:font-extrabold tw:border tw:border-solid tw:whitespace-nowrap {{ $netCashFlowBadgeClass }}">
                    {{ $netCashFlowBadgeText }}
                </span>
            </div>

            <div class="tw:grid tw:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:gap-3 tw:max-[992px]:[grid-template-columns:1fr]">
                <div class="tw:p-[14px] tw:rounded-[14px] tw:bg-[#f8fafc] tw:border tw:border-solid tw:border-[#edf2f7] tw:[&_span]:block tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:mb-[6px] tw:[&_strong]:text-[18px] tw:[&_strong]:font-extrabold">
                    <span>Thu trong kỳ lọc</span>
                    <strong class="tw:text-[#0f172a]">{{ $cashInText }}</strong>
                </div>

                <div class="tw:p-[14px] tw:rounded-[14px] tw:bg-[#f8fafc] tw:border tw:border-solid tw:border-[#edf2f7] tw:[&_span]:block tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:mb-[6px] tw:[&_strong]:text-[18px] tw:[&_strong]:font-extrabold">
                    <span>Chi trong kỳ lọc</span>
                    <strong class="tw:text-[#0f172a]">{{ $cashOutText }}</strong>
                </div>

                <div class="tw:p-[14px] tw:rounded-[14px] tw:bg-[#f8fafc] tw:border tw:border-solid tw:border-[#edf2f7] tw:[&_span]:block tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:mb-[6px] tw:[&_strong]:text-[18px] tw:[&_strong]:font-extrabold">
                    <span>Dòng tiền ròng</span>
                    <strong class="{{ $netCashFlowClass }}">
                        {{ $netCashFlowText }}
                    </strong>
                </div>

                <div class="tw:p-[14px] tw:rounded-[14px] tw:bg-[#f8fafc] tw:border tw:border-solid tw:border-[#edf2f7] tw:[&_span]:block tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:mb-[6px] tw:[&_strong]:text-[18px] tw:[&_strong]:font-extrabold">
                    <span>Chi chờ duyệt / giải ngân</span>
                    <strong class="tw:text-[#0f172a]">{{ $pendingDisbursementText }}</strong>
                </div>
            </div>
        </div>

        <div class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[20px] tw:p-[18px] tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)]">
            <div class="tw:flex tw:justify-between tw:items-start tw:gap-3 tw:mb-[14px] tw:[&_h2]:m-0 tw:[&_h2]:text-[20px] tw:[&_h2]:text-[#0f172a] tw:[&_h2]:font-extrabold">
                <div>
                    <div class="tw:text-[11px] tw:text-[#5b6b7f] tw:font-extrabold tw:tracking-[.08em] tw:uppercase tw:mb-1">Business Navigation</div>
                    <h2>Điều hướng tài chính</h2>
                </div>
            </div>

            <div class="tw:grid tw:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:gap-[10px] tw:max-[992px]:[grid-template-columns:1fr]">
                <a href="{{ route('finance.customer-debts.index') }}" class="tw:flex tw:items-start tw:gap-3 tw:p-[14px] tw:rounded-[14px] tw:bg-[#fbfdff] tw:border tw:border-solid tw:border-[#e7edf5] tw:no-underline tw:text-inherit tw:[transition:.2s_ease] tw:hover:[transform:translateY(-1px)] tw:hover:shadow-[0_10px_22px_rgba(15,23,42,0.06)] tw:hover:text-inherit tw:[&_i]:w-[38px] tw:[&_i]:h-[38px] tw:[&_i]:shrink-0 tw:[&_i]:grow-0 tw:[&_i]:basis-auto tw:[&_i]:rounded-xl tw:[&_i]:flex tw:[&_i]:items-center tw:[&_i]:justify-center tw:[&_i]:bg-[#eff6ff] tw:[&_i]:text-[#2563eb] tw:[&_i]:text-[16px] tw:[&_strong]:block tw:[&_strong]:text-[14px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#0f172a] tw:[&_strong]:mb-[3px] tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:leading-[1.5]">
                    <i class="bi bi-people"></i>
                    <div>
                        <strong>Công nợ khách hàng</strong>
                        <span>Theo dõi khoản phải thu</span>
                    </div>
                </a>

                <a href="{{ route('finance.customer-debts.payment-history') }}" class="tw:flex tw:items-start tw:gap-3 tw:p-[14px] tw:rounded-[14px] tw:bg-[#fbfdff] tw:border tw:border-solid tw:border-[#e7edf5] tw:no-underline tw:text-inherit tw:[transition:.2s_ease] tw:hover:[transform:translateY(-1px)] tw:hover:shadow-[0_10px_22px_rgba(15,23,42,0.06)] tw:hover:text-inherit tw:[&_i]:w-[38px] tw:[&_i]:h-[38px] tw:[&_i]:shrink-0 tw:[&_i]:grow-0 tw:[&_i]:basis-auto tw:[&_i]:rounded-xl tw:[&_i]:flex tw:[&_i]:items-center tw:[&_i]:justify-center tw:[&_i]:bg-[#eff6ff] tw:[&_i]:text-[#2563eb] tw:[&_i]:text-[16px] tw:[&_strong]:block tw:[&_strong]:text-[14px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#0f172a] tw:[&_strong]:mb-[3px] tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:leading-[1.5]">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <strong>Lịch sử thanh toán</strong>
                        <span>Xem các lần thu tiền</span>
                    </div>
                </a>

                <a href="{{ route('finance.payments.index') }}" class="tw:flex tw:items-start tw:gap-3 tw:p-[14px] tw:rounded-[14px] tw:bg-[#fbfdff] tw:border tw:border-solid tw:border-[#e7edf5] tw:no-underline tw:text-inherit tw:[transition:.2s_ease] tw:hover:[transform:translateY(-1px)] tw:hover:shadow-[0_10px_22px_rgba(15,23,42,0.06)] tw:hover:text-inherit tw:[&_i]:w-[38px] tw:[&_i]:h-[38px] tw:[&_i]:shrink-0 tw:[&_i]:grow-0 tw:[&_i]:basis-auto tw:[&_i]:rounded-xl tw:[&_i]:flex tw:[&_i]:items-center tw:[&_i]:justify-center tw:[&_i]:bg-[#eff6ff] tw:[&_i]:text-[#2563eb] tw:[&_i]:text-[16px] tw:[&_strong]:block tw:[&_strong]:text-[14px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#0f172a] tw:[&_strong]:mb-[3px] tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:leading-[1.5]">
                    <i class="bi bi-credit-card-2-front"></i>
                    <div>
                        <strong>Phiếu chi</strong>
                        <span>Kiểm soát dòng tiền ra</span>
                    </div>
                </a>

                <a href="{{ route('finance.receipts.index') }}" class="tw:flex tw:items-start tw:gap-3 tw:p-[14px] tw:rounded-[14px] tw:bg-[#fbfdff] tw:border tw:border-solid tw:border-[#e7edf5] tw:no-underline tw:text-inherit tw:[transition:.2s_ease] tw:hover:[transform:translateY(-1px)] tw:hover:shadow-[0_10px_22px_rgba(15,23,42,0.06)] tw:hover:text-inherit tw:[&_i]:w-[38px] tw:[&_i]:h-[38px] tw:[&_i]:shrink-0 tw:[&_i]:grow-0 tw:[&_i]:basis-auto tw:[&_i]:rounded-xl tw:[&_i]:flex tw:[&_i]:items-center tw:[&_i]:justify-center tw:[&_i]:bg-[#eff6ff] tw:[&_i]:text-[#2563eb] tw:[&_i]:text-[16px] tw:[&_strong]:block tw:[&_strong]:text-[14px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#0f172a] tw:[&_strong]:mb-[3px] tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:leading-[1.5]">
                    <i class="bi bi-cash-coin"></i>
                    <div>
                        <strong>Phiếu thu</strong>
                        <span>Kiểm soát dòng tiền vào</span>
                    </div>
                </a>

                <a href="{{ route('finance.accounts.index') }}" class="tw:flex tw:items-start tw:gap-3 tw:p-[14px] tw:rounded-[14px] tw:bg-[#fbfdff] tw:border tw:border-solid tw:border-[#e7edf5] tw:no-underline tw:text-inherit tw:[transition:.2s_ease] tw:hover:[transform:translateY(-1px)] tw:hover:shadow-[0_10px_22px_rgba(15,23,42,0.06)] tw:hover:text-inherit tw:[&_i]:w-[38px] tw:[&_i]:h-[38px] tw:[&_i]:shrink-0 tw:[&_i]:grow-0 tw:[&_i]:basis-auto tw:[&_i]:rounded-xl tw:[&_i]:flex tw:[&_i]:items-center tw:[&_i]:justify-center tw:[&_i]:bg-[#eff6ff] tw:[&_i]:text-[#2563eb] tw:[&_i]:text-[16px] tw:[&_strong]:block tw:[&_strong]:text-[14px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#0f172a] tw:[&_strong]:mb-[3px] tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:leading-[1.5]">
                    <i class="bi bi-bank"></i>
                    <div>
                        <strong>Quỹ & Tài khoản</strong>
                        <span>Số dư theo quỹ / ngân hàng</span>
                    </div>
                </a>

                <a href="{{ route('finance.reports') }}" class="tw:flex tw:items-start tw:gap-3 tw:p-[14px] tw:rounded-[14px] tw:bg-[#fbfdff] tw:border tw:border-solid tw:border-[#e7edf5] tw:no-underline tw:text-inherit tw:[transition:.2s_ease] tw:hover:[transform:translateY(-1px)] tw:hover:shadow-[0_10px_22px_rgba(15,23,42,0.06)] tw:hover:text-inherit tw:[&_i]:w-[38px] tw:[&_i]:h-[38px] tw:[&_i]:shrink-0 tw:[&_i]:grow-0 tw:[&_i]:basis-auto tw:[&_i]:rounded-xl tw:[&_i]:flex tw:[&_i]:items-center tw:[&_i]:justify-center tw:[&_i]:bg-[#eff6ff] tw:[&_i]:text-[#2563eb] tw:[&_i]:text-[16px] tw:[&_strong]:block tw:[&_strong]:text-[14px] tw:[&_strong]:font-extrabold tw:[&_strong]:text-[#0f172a] tw:[&_strong]:mb-[3px] tw:[&_span]:text-[#475569] tw:[&_span]:text-[12px] tw:[&_span]:leading-[1.5]">
                    <i class="bi bi-bar-chart"></i>
                    <div>
                        <strong>Báo cáo</strong>
                        <span>Xem biểu đồ và tổng hợp</span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    {{-- BẢNG LỢI NHUẬN ĐƠN HÀNG --}}
    <section class="tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e7edf5] tw:rounded-[20px] tw:p-[18px] tw:shadow-[0_12px_28px_rgba(15,23,42,0.06)] tw:mt-4">
        <div class="tw:flex tw:justify-between tw:items-start tw:gap-3 tw:mb-[14px] tw:[&_h2]:m-0 tw:[&_h2]:text-[20px] tw:[&_h2]:text-[#0f172a] tw:[&_h2]:font-extrabold">
            <div>
                <div class="tw:text-[11px] tw:text-[#5b6b7f] tw:font-extrabold tw:tracking-[.08em] tw:uppercase tw:mb-1">Order Profit</div>
                <h2>Đơn hàng lợi nhuận gần đây</h2>
            </div>
        </div>

        <div class="tw:mb-3 tw:py-3 tw:px-[14px] tw:rounded-xl tw:bg-[#f8fafc] tw:border tw:border-solid tw:border-[#edf2f7] tw:text-[#475569] tw:text-[13px] tw:leading-[1.6]">
            Bộ lọc phía trên sẽ áp dụng cho KPI, công nợ, dòng tiền và danh sách đơn hàng lợi nhuận.
        </div>

        <div class="tw:overflow-x-auto">
            <table class="tw:w-full tw:[border-collapse:separate] tw:[border-spacing:0] tw:min-w-[860px]">
                <thead>
                    <tr>
                        <th class="tw:text-left tw:p-3 tw:text-[12px] tw:font-extrabold tw:uppercase tw:tracking-[.04em] tw:text-[#5b6b7f] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#e7edf5]">Mã đơn</th>
                        <th class="tw:text-left tw:p-3 tw:text-[12px] tw:font-extrabold tw:uppercase tw:tracking-[.04em] tw:text-[#5b6b7f] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#e7edf5]">Ngày</th>
                        <th class="tw:text-left tw:p-3 tw:text-[12px] tw:font-extrabold tw:uppercase tw:tracking-[.04em] tw:text-[#5b6b7f] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#e7edf5]">Doanh thu</th>
                        <th class="tw:text-left tw:p-3 tw:text-[12px] tw:font-extrabold tw:uppercase tw:tracking-[.04em] tw:text-[#5b6b7f] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#e7edf5]">Giá vốn</th>
                        <th class="tw:text-left tw:p-3 tw:text-[12px] tw:font-extrabold tw:uppercase tw:tracking-[.04em] tw:text-[#5b6b7f] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#e7edf5]">Lợi nhuận gộp</th>
                        <th class="tw:text-left tw:p-3 tw:text-[12px] tw:font-extrabold tw:uppercase tw:tracking-[.04em] tw:text-[#5b6b7f] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#e7edf5]">Biên lợi nhuận</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profitRows as $row)
                        <tr class="tw:hover:bg-[#fafcff]">
                            <td class="tw:p-3 tw:text-[13px] tw:text-[#0f172a] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef2f7] tw:align-middle"><strong>{{ $row->codeText }}</strong></td>
                            <td class="tw:p-3 tw:text-[13px] tw:text-[#0f172a] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef2f7] tw:align-middle">
                                {{ $row->dateText }}
                            </td>
                            <td class="tw:p-3 tw:text-[13px] tw:text-[#0f172a] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef2f7] tw:align-middle">{{ $row->saleText }}</td>
                            <td class="tw:p-3 tw:text-[13px] tw:text-[#0f172a] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef2f7] tw:align-middle">{{ $row->costText }}</td>
                            <td class="tw:p-3 tw:text-[13px] tw:text-[#0f172a] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef2f7] tw:align-middle {{ $row->profitClass }}">
                                {{ $row->profitText }}
                            </td>
                            <td class="tw:p-3 tw:text-[13px] tw:text-[#0f172a] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef2f7] tw:align-middle">
                                <span class="tw:inline-flex tw:items-center tw:py-[6px] tw:px-[9px] tw:rounded-[999px] tw:text-[12px] tw:font-extrabold {{ $row->marginClass }}">
                                    {{ $row->marginText }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tw:p-3 tw:text-[13px] tw:text-[#0f172a] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef2f7] tw:align-middle tw:text-center tw:text-[#475569] tw:p-[22px]">
                                Không có dữ liệu phù hợp với bộ lọc.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection