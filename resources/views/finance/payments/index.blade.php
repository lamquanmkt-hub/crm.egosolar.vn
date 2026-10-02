@extends('layouts.app')

@section('title', 'Phiếu chi')

@section('content')
<div class="container-fluid tw:py-6">
    <style>
        .finance-page-out {
            --primary-color: #dc2626;
            --primary-soft: rgba(220, 38, 38, 0.08);
            --primary-border: rgba(220, 38, 38, 0.16);
            --text-main: #0f172a;
            --text-soft: #64748b;
            --surface: #ffffff;
            --border-soft: #e2e8f0;
            --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.06);
            --shadow-hover: 0 14px 35px rgba(220, 38, 38, 0.12);
            --radius-xl: 24px;
            --radius-lg: 18px;
        }

        .finance-page-out {
            color: var(--text-main);
        }

        .finance-hero-out {
            background:
                radial-gradient(circle at top right, rgba(220, 38, 38, 0.12), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #fff9f9 100%);
            border: 1px solid var(--primary-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            padding: 24px;
            margin-bottom: 20px;
        }

        .finance-badge-out {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 13px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary-color);
            font-weight: 700;
            font-size: 12px;
        }

        .finance-title-out {
            font-size: 30px;
            line-height: 1.15;
            font-weight: 800;
            margin: 10px 0 8px;
            color: var(--text-main);
        }

        .finance-subtitle-out {
            color: var(--text-soft);
            font-size: 13px;
            max-width: 760px;
        }

        .finance-action-btn-out {
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 13px;
            border: none;
            transition: all .2s ease;
        }

        .finance-action-btn-out:hover {
            transform: translateY(-1px);
        }

        .finance-btn-light-out {
            background: #fff;
            border: 1px solid var(--border-soft);
            color: var(--text-main);
        }

        .finance-btn-primary-out {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(220, 38, 38, 0.24);
        }

        .finance-card-out {
            background: #fff;
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .stat-card-out {
            padding: 18px;
            height: 100%;
            transition: all .25s ease;
        }

        .stat-card-out:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .stat-label-out {
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .stat-value-out {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--text-main);
        }

        .stat-icon-out {
            width: 46px;
            height: 46px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: var(--primary-soft);
            color: var(--primary-color);
        }

        .block-header-out {
            padding: 18px 20px 0;
        }

        .block-title-out {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
        }

        .block-subtitle-out {
            color: var(--text-soft);
            font-size: 13px;
            margin-top: 4px;
        }

        .block-body-out {
            padding: 18px 20px 20px;
        }

        .modern-label-out {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .modern-input-out,
        .modern-select-out {
            border-radius: 999px;
            border: 1px solid #dbe3ee;
            min-height: 42px;
            padding: 8px 14px;
            font-size: 13px;
            box-shadow: none !important;
        }

        .modern-input-out:focus,
        .modern-select-out:focus {
            border-color: #fecaca;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.10) !important;
        }

        .modern-toolbar-out {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .modern-table-wrap-out {
            overflow: hidden;
            border-radius: 0 0 18px 18px;
        }

        .modern-table-out {
            margin: 0;
        }

        .modern-table-out thead th {
            font-size: 12px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .modern-table-out tbody td {
            padding-top: 14px;
            padding-bottom: 14px;
            border-color: #eef2f7;
            vertical-align: middle;
            font-size: 13px;
        }

        .modern-pill-out {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
        }

        .pill-red-out {
            background: rgba(220, 38, 38, 0.10);
            color: #dc2626;
        }

        .pill-gray-out {
            background: #f1f5f9;
            color: #334155;
        }

        .money-out {
            color: #dc2626;
            font-weight: 800;
            font-size: 14px;
        }

        .empty-state-out {
            padding: 48px 20px;
            text-align: center;
        }

        .empty-icon-out {
            width: 64px;
            height: 64px;
            border-radius: 999px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 800;
        }

        .empty-title-out {
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 6px;
        }

        .empty-subtitle-out {
            color: var(--text-soft);
            max-width: 420px;
            margin: 0 auto;
            font-size: 13px;
        }
    </style>

    <div class="finance-page-out">
        <div class="finance-hero-out">
            <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-4">
                <div>
                    <span class="finance-badge-out">💸 Quản lý dòng tiền ra</span>
                    <h1 class="finance-title-out">Chi / Phiếu chi</h1>
                    <div class="finance-subtitle-out">
                        Theo dõi các khoản chi, lọc nhanh, tạo phiếu chi mới và quản lý lịch sử giao dịch trên giao diện hiện đại, gọn gàng.
                    </div>
                </div>

                <div class="modern-toolbar-out">
                    <x-ui.button variant="none" size="none" class="finance-action-btn-out finance-btn-light-out" href="{{ route('finance.payments.index') }}">
                        Danh sách
                    </x-ui.button>
                    <x-ui.button variant="none" size="none" class="finance-action-btn-out finance-btn-primary-out" href="{{ route('finance.payments.create') }}">
                        + Tạo phiếu chi
                    </x-ui.button>
                </div>
            </div>
        </div>

        @if(session('success'))
            <x-ui.alert variant="success" class="tw:border-0 tw:rounded-[1rem] tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:mb-6">
                {{ session('success') }}
            </x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="danger" class="tw:border-0 tw:rounded-[1rem] tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:mb-6">
                <div class="tw:font-bold tw:mb-2">Có lỗi cần sửa</div>
                <ul class="tw:mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="tw:row tw:g-3 tw:mb-6">
            <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                <div class="finance-card-out stat-card-out">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label-out">Tổng phiếu chi</div>
                            <div class="stat-value-out">{{ number_format($stats['total_count']) }}</div>
                        </div>
                        <div class="stat-icon-out">🧾</div>
                    </div>
                </div>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                <div class="finance-card-out stat-card-out">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label-out">Tổng tiền đã chi</div>
                            <div class="stat-value-out">{{ number_format($stats['total_amount'], 0, ',', '.') }} đ</div>
                        </div>
                        <div class="stat-icon-out">💳</div>
                    </div>
                </div>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                <div class="finance-card-out stat-card-out">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label-out">Chi hôm nay</div>
                            <div class="stat-value-out">{{ number_format($stats['today_amount'], 0, ',', '.') }} đ</div>
                        </div>
                        <div class="stat-icon-out">📅</div>
                    </div>
                </div>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                <div class="finance-card-out stat-card-out">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label-out">Chi tháng này</div>
                            <div class="stat-value-out">{{ number_format($stats['this_month_amount'], 0, ',', '.') }} đ</div>
                        </div>
                        <div class="stat-icon-out">📉</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="finance-card-out tw:mb-6">
            <div class="block-header-out">
                <h3 class="block-title-out">Bộ lọc tìm kiếm</h3>
                <div class="block-subtitle-out">Mặc định đang lọc trong tháng hiện tại.</div>
            </div>

            <div class="block-body-out">
                <form method="GET" action="{{ route('finance.payments.index') }}">
                    <div class="tw:row tw:g-3">
                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label-out">Tìm kiếm</label>
                            <x-ui.input
                                type="text"
                                name="q"
                                class="modern-input-out"
                                placeholder="Mã phiếu, người nhận, SĐT..."
                                value="{{ request('q') }}" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label-out">Từ ngày</label>
                            <x-ui.input type="date" name="date_from" class="modern-input-out" value="{{ request('date_from', $defaultDateFrom ?? now()->startOfMonth()->format('Y-m-d')) }}" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label-out">Đến ngày</label>
                            <x-ui.input type="date" name="date_to" class="modern-input-out" value="{{ request('date_to', $defaultDateTo ?? now()->endOfMonth()->format('Y-m-d')) }}" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label-out">Loại chi</label>
                            <x-ui.select name="category" class="modern-select-out">
                                <option value="">Tất cả</option>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label-out">Phương thức</label>
                            <x-ui.select name="payment_method" class="modern-select-out">
                                <option value="">Tất cả</option>
                                @foreach($paymentMethods as $key => $label)
                                    <option value="{{ $key }}" @selected(request('payment_method') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-1 tw:md:col12-6 tw:flex tw:items-end">
                            <x-ui.button variant="none" size="none" type="submit" class="finance-action-btn-out finance-btn-primary-out tw:w-full">Lọc</x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="finance-card-out tw:mb-6">
            <div class="block-header-out">
                <h3 class="block-title-out">{{ $mode === 'create' ? 'Tạo phiếu chi mới' : 'Tạo nhanh phiếu chi' }}</h3>
                <div class="block-subtitle-out">Nhập nhanh thông tin để lưu phiếu chi.</div>
            </div>

            <div class="block-body-out">
                <form method="POST" action="{{ route('finance.payments.store') }}">
                    @csrf

                    <div class="tw:row tw:g-3">
                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label-out">Ngày chi</label>
                            <x-ui.input type="date" name="payment_date" class="modern-input-out" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required />
                        </div>

                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label-out">Người nhận</label>
                            <x-ui.input type="text" name="payee_name" class="modern-input-out" value="{{ old('payee_name') }}" placeholder="VD: Công ty ABC" required />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label-out">Số điện thoại</label>
                            <x-ui.input type="text" name="payee_phone" class="modern-input-out" value="{{ old('payee_phone') }}" placeholder="Không bắt buộc" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label-out">Loại chi</label>
                            <x-ui.select name="category" class="modern-select-out" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category', 'chi_nha_cung_cap') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label-out">Phương thức</label>
                            <x-ui.select name="payment_method" class="modern-select-out" required>
                                @foreach($paymentMethods as $key => $label)
                                    <option value="{{ $key }}" @selected(old('payment_method', 'cash') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label-out">Số tiền</label>
                            <x-ui.input type="number" min="1000" step="any" name="amount" class="modern-input-out" value="{{ old('amount') }}" placeholder="VD: 3000000" required />
                        </div>

                        <div class="tw:min-[75rem]:col12-9 tw:md:col12-6">
                            <label class="modern-label-out">Ghi chú</label>
                            <x-ui.input type="text" name="note" class="modern-input-out" value="{{ old('note') }}" placeholder="Nội dung chi tiền..." />
                        </div>

                        <div class="tw:col12-12 tw:flex tw:justify-end tw:gap-2 pt-2">
                            <x-ui.button variant="none" size="none" class="finance-action-btn-out finance-btn-light-out" href="{{ route('finance.payments.index') }}">
                                Làm mới
                            </x-ui.button>
                            <x-ui.button variant="none" size="none" class="finance-action-btn-out finance-btn-primary-out" type="submit">
                                Lưu phiếu chi
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="finance-card-out">
            <div class="block-header-out tw:flex tw:justify-between tw:items-center flex-wrap tw:gap-2">
                <div>
                    <h3 class="block-title-out">Danh sách phiếu chi</h3>
                    <div class="block-subtitle-out">Toàn bộ lịch sử phiếu chi trong phạm vi lọc hiện tại.</div>
                </div>

                <span class="modern-pill-out pill-gray-out">{{ $payments->total() }} phiếu</span>
            </div>

            <div class="modern-table-wrap-out">
                <div class="table-responsive">
                    <table class="table modern-table-out align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Mã phiếu</th>
                                <th>Ngày</th>
                                <th>Người nhận</th>
                                <th>Loại chi</th>
                                <th>Phương thức</th>
                                <th class="tw:text-right">Số tiền</th>
                                <th class="pe-4">Ghi chú</th>
                                <th class="pe-4 tw:text-center">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td class="ps-4 tw:font-bold">{{ $payment->code }}</td>
                                    <td>{{ optional($payment->payment_date)->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="tw:font-bold">{{ $payment->payee_name }}</div>
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">{{ $payment->payee_phone }}</div>
                                    </td>
                                    <td>
                                        <span class="modern-pill-out pill-red-out">
                                            {{ $categories[$payment->category] ?? $payment->category }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="modern-pill-out pill-gray-out">
                                            {{ $paymentMethods[$payment->payment_method] ?? $payment->payment_method }}
                                        </span>
                                    </td>
                                    <td class="tw:text-right money-out">{{ number_format($payment->amount, 0, ',', '.') }} đ</td>
                                    <td class="tw:text-[rgba(33,37,41,0.75)]!">{{ $payment->note }}</td>
<td class="pe-4 tw:text-center">
    <form action="{{ route('finance.payments.destroy', $payment->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa phiếu chi này không?');" class="d-inline">
        @csrf
        @method('DELETE')
        <x-ui.button variant="outline-danger" size="sm" type="submit" class="rounded-pill tw:px-4">
            Xóa
        </x-ui.button>
    </form>
</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state-out">
                                            <div class="empty-icon-out">💸</div>
                                            <div class="empty-title-out">Chưa có phiếu chi nào</div>
                                            <div class="empty-subtitle-out">
                                                Hãy tạo phiếu chi đầu tiên để bắt đầu theo dõi dòng tiền ra.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($payments->count())
                    <div class="tw:p-6">
                        {{ $payments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection