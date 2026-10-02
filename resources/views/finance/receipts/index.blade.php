@extends('layouts.app')

@section('title', 'Phiếu thu')

@section('content')
<div class="container-fluid tw:py-6">
    <style>
        .finance-page {
            --primary-color: #2563eb;
            --primary-soft: rgba(37, 99, 235, 0.08);
            --primary-border: rgba(37, 99, 235, 0.16);
            --text-main: #0f172a;
            --text-soft: #64748b;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --border-soft: #e2e8f0;
            --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.06);
            --shadow-hover: 0 14px 35px rgba(37, 99, 235, 0.12);
            --radius-xl: 24px;
            --radius-lg: 18px;
        }

        .finance-page {
            color: var(--text-main);
        }

        .finance-hero {
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.14), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid var(--primary-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            padding: 24px;
            margin-bottom: 20px;
        }

        .finance-badge {
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

        .finance-title {
            font-size: 30px;
            line-height: 1.15;
            font-weight: 800;
            margin: 10px 0 8px;
            color: var(--text-main);
        }

        .finance-subtitle {
            color: var(--text-soft);
            font-size: 13px;
            max-width: 760px;
        }

        .finance-action-btn {
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 13px;
            border: none;
            transition: all .2s ease;
        }

        .finance-action-btn:hover {
            transform: translateY(-1px);
        }

        .finance-btn-light {
            background: #fff;
            border: 1px solid var(--border-soft);
            color: var(--text-main);
        }

        .finance-btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.22);
        }

        .finance-card {
            background: var(--surface);
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .stat-card {
            padding: 18px;
            height: 100%;
            transition: all .25s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .stat-label {
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--text-main);
        }

        .stat-icon {
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

        .block-header {
            padding: 18px 20px 0;
        }

        .block-title {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
        }

        .block-subtitle {
            color: var(--text-soft);
            font-size: 13px;
            margin-top: 4px;
        }

        .block-body {
            padding: 18px 20px 20px;
        }

        .modern-label {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .modern-input,
        .modern-select {
            border-radius: 999px;
            border: 1px solid #dbe3ee;
            min-height: 42px;
            padding: 8px 14px;
            font-size: 13px;
            box-shadow: none !important;
        }

        .modern-input:focus,
        .modern-select:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10) !important;
        }

        .modern-toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .modern-table-wrap {
            overflow: hidden;
            border-radius: 0 0 18px 18px;
        }

        .modern-table {
            margin: 0;
        }

        .modern-table thead th {
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

        .modern-table tbody td {
            padding-top: 14px;
            padding-bottom: 14px;
            border-color: #eef2f7;
            vertical-align: middle;
            font-size: 13px;
        }

        .modern-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
        }

        .pill-blue {
            background: rgba(37, 99, 235, 0.10);
            color: #1d4ed8;
        }

        .pill-gray {
            background: #f1f5f9;
            color: #334155;
        }

        .money-in {
            color: #047857;
            font-weight: 800;
            font-size: 14px;
        }

        .empty-state {
            padding: 48px 20px;
            text-align: center;
        }

        .empty-icon {
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

        .empty-title {
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 6px;
        }

        .empty-subtitle {
            color: var(--text-soft);
            max-width: 420px;
            margin: 0 auto;
            font-size: 13px;
        }
    </style>

    <div class="finance-page">
        <div class="finance-hero">
            <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-4">
                <div>
                    <span class="finance-badge">💰 Quản lý dòng tiền vào</span>
                    <h1 class="finance-title">Thu / Phiếu thu</h1>
                    <div class="finance-subtitle">
                        Theo dõi các khoản thu, lọc nhanh, tạo phiếu thu mới và quản lý lịch sử giao dịch trên một giao diện gọn gàng, dễ nhìn.
                    </div>
                </div>

                <div class="modern-toolbar">
                    <x-ui.button variant="none" size="none" class="finance-action-btn finance-btn-light" href="{{ route('finance.receipts.index') }}">
                        Danh sách
                    </x-ui.button>
                    <x-ui.button variant="none" size="none" class="finance-action-btn finance-btn-primary" href="{{ route('finance.receipts.create') }}">
                        + Tạo phiếu thu
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
                <div class="finance-card stat-card">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label">Tổng phiếu thu</div>
                            <div class="stat-value">{{ number_format($stats['total_count']) }}</div>
                        </div>
                        <div class="stat-icon">🧾</div>
                    </div>
                </div>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                <div class="finance-card stat-card">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label">Tổng tiền đã thu</div>
                            <div class="stat-value">{{ number_format($stats['total_amount'], 0, ',', '.') }} đ</div>
                        </div>
                        <div class="stat-icon">💵</div>
                    </div>
                </div>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                <div class="finance-card stat-card">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label">Thu hôm nay</div>
                            <div class="stat-value">{{ number_format($stats['today_amount'], 0, ',', '.') }} đ</div>
                        </div>
                        <div class="stat-icon">📅</div>
                    </div>
                </div>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                <div class="finance-card stat-card">
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="stat-label">Thu tháng này</div>
                            <div class="stat-value">{{ number_format($stats['this_month_amount'], 0, ',', '.') }} đ</div>
                        </div>
                        <div class="stat-icon">📈</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="finance-card tw:mb-6">
            <div class="block-header">
                <h3 class="block-title">Bộ lọc tìm kiếm</h3>
                <div class="block-subtitle">Mặc định đang lọc trong tháng hiện tại.</div>
            </div>

            <div class="block-body">
                <form method="GET" action="{{ route('finance.receipts.index') }}">
                    <div class="tw:row tw:g-3">
                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label">Tìm kiếm</label>
                            <x-ui.input
                                type="text"
                                name="q"
                                class="modern-input"
                                placeholder="Mã phiếu, người nộp, SĐT..."
                                value="{{ request('q') }}" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label">Từ ngày</label>
                            <x-ui.input type="date" name="date_from" class="modern-input" value="{{ request('date_from', $defaultDateFrom ?? now()->startOfMonth()->format('Y-m-d')) }}" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label">Đến ngày</label>
                            <x-ui.input type="date" name="date_to" class="modern-input" value="{{ request('date_to', $defaultDateTo ?? now()->endOfMonth()->format('Y-m-d')) }}" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label">Loại thu</label>
                            <x-ui.select name="category" class="modern-select">
                                <option value="">Tất cả</option>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label">Phương thức</label>
                            <x-ui.select name="payment_method" class="modern-select">
                                <option value="">Tất cả</option>
                                @foreach($paymentMethods as $key => $label)
                                    <option value="{{ $key }}" @selected(request('payment_method') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-1 tw:md:col12-6 tw:flex tw:items-end">
                            <x-ui.button variant="none" size="none" type="submit" class="finance-action-btn finance-btn-primary tw:w-full">Lọc</x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="finance-card tw:mb-6">
            <div class="block-header">
                <h3 class="block-title">{{ $mode === 'create' ? 'Tạo phiếu thu mới' : 'Tạo nhanh phiếu thu' }}</h3>
                <div class="block-subtitle">Nhập nhanh thông tin để lưu phiếu thu.</div>
            </div>

            <div class="block-body">
                <form method="POST" action="{{ route('finance.receipts.store') }}">
                    @csrf

                    <div class="tw:row tw:g-3">
                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label">Ngày thu</label>
                            <x-ui.input type="date" name="receipt_date" class="modern-input" value="{{ old('receipt_date', now()->format('Y-m-d')) }}" required />
                        </div>

                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label">Người nộp</label>
                            <x-ui.input type="text" name="payer_name" class="modern-input" value="{{ old('payer_name') }}" placeholder="VD: Nguyễn Văn A" required />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label">Số điện thoại</label>
                            <x-ui.input type="text" name="payer_phone" class="modern-input" value="{{ old('payer_phone') }}" placeholder="Không bắt buộc" />
                        </div>

                        <div class="tw:min-[75rem]:col12-2 tw:md:col12-6">
                            <label class="modern-label">Loại thu</label>
                            <x-ui.select name="category" class="modern-select" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category', 'thu_khach_hang') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label">Phương thức</label>
                            <x-ui.select name="payment_method" class="modern-select" required>
                                @foreach($paymentMethods as $key => $label)
                                    <option value="{{ $key }}" @selected(old('payment_method', 'cash') === $key)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:min-[75rem]:col12-3 tw:md:col12-6">
                            <label class="modern-label">Số tiền</label>
                            <x-ui.input type="number" min="1000" step="any" name="amount" class="modern-input" value="{{ old('amount') }}" placeholder="VD: 5000000" required />
                        </div>

                        <div class="tw:min-[75rem]:col12-9 tw:md:col12-6">
                            <label class="modern-label">Ghi chú</label>
                            <x-ui.input type="text" name="note" class="modern-input" value="{{ old('note') }}" placeholder="Nội dung thu tiền..." />
                        </div>

                        <div class="tw:col12-12 tw:flex tw:justify-end tw:gap-2 pt-2">
                            <x-ui.button variant="none" size="none" class="finance-action-btn finance-btn-light" href="{{ route('finance.receipts.index') }}">
                                Làm mới
                            </x-ui.button>
                            <x-ui.button variant="none" size="none" class="finance-action-btn finance-btn-primary" type="submit">
                                Lưu phiếu thu
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="finance-card">
            <div class="block-header tw:flex tw:justify-between tw:items-center flex-wrap tw:gap-2">
                <div>
                    <h3 class="block-title">Danh sách phiếu thu</h3>
                    <div class="block-subtitle">Toàn bộ lịch sử phiếu thu trong phạm vi lọc hiện tại.</div>
                </div>

                <span class="modern-pill pill-gray">{{ $receipts->total() }} phiếu</span>
            </div>

            <div class="modern-table-wrap">
                <div class="table-responsive">
                    <table class="table modern-table align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Mã phiếu</th>
                                <th>Ngày</th>
                                <th>Người nộp</th>
                                <th>Loại thu</th>
                                <th>Phương thức</th>
                                <th class="tw:text-right">Số tiền</th>
                                <th class="pe-4">Ghi chú</th>
                                <th class="pe-4 tw:text-center">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receipts as $receipt)
                                <tr>
                                    <td class="ps-4 tw:font-bold">{{ $receipt->code }}</td>
                                    <td>{{ optional($receipt->receipt_date)->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="tw:font-bold">{{ $receipt->payer_name }}</div>
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">{{ $receipt->payer_phone }}</div>
                                    </td>
                                    <td>
                                        <span class="modern-pill pill-blue">
                                            {{ $categories[$receipt->category] ?? $receipt->category }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="modern-pill pill-gray">
                                            {{ $paymentMethods[$receipt->payment_method] ?? $receipt->payment_method }}
                                        </span>
                                    </td>
                                    <td class="tw:text-right money-in">{{ number_format($receipt->amount, 0, ',', '.') }} đ</td>
                                    <td class="tw:text-[rgba(33,37,41,0.75)]!">{{ $receipt->note }}</td>
<td class="pe-4 tw:text-center">
    <form action="{{ route('finance.receipts.destroy', $receipt->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa phiếu thu này không?');" class="d-inline">
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
                                        <div class="empty-state">
                                            <div class="empty-icon">💰</div>
                                            <div class="empty-title">Chưa có phiếu thu nào</div>
                                            <div class="empty-subtitle">
                                                Hãy tạo phiếu thu đầu tiên để bắt đầu theo dõi dòng tiền vào.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($receipts->count())
                    <div class="tw:p-6">
                        {{ $receipts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection