@extends('layouts.app')

@section('title', $config['title'])

@section('content')
@php
    $money = fn($value) => number_format((float) ($value ?? 0), 0, ',', '.') . ' đ';
    $statusMap = [
        'unpaid' => ['Chưa thanh toán', 'danger'],
        'partial' => ['Thanh toán một phần', 'warning'],
        'paid' => ['Đã thanh toán', 'success'],
        'overdue' => ['Quá hạn', 'overdue'],
    ];
    $methodMap = [
        'bank' => 'Chuyển khoản',
        'cash' => 'Tiền mặt',
        'other' => 'Khác',
        'opening' => 'Số dư đầu kỳ',
    ];
@endphp

<div class="finance-ledger-page">
    <div class="fl-hero">
        <div class="fl-hero__copy">
            <div class="fl-kicker">TÀI CHÍNH KẾ TOÁN · NỢ PHẢI TRẢ</div>
            <h1><i class="bi {{ $config['icon'] }}"></i>{{ $config['title'] }}</h1>
            <p>{{ $config['description'] }}</p>
        </div>
        <div class="fl-hero__actions">
            @if($schemaReady)
                <a class="fl-btn fl-btn-light" href="{{ route('finance.ledger.export', array_merge(['direction' => $direction, 'category' => $category], request()->query())) }}">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Xuất CSV
                </a>
                <button type="button" class="fl-btn fl-btn-primary" onclick="document.getElementById('fl-create').open = true">
                    <i class="bi bi-plus-lg"></i> Thêm công nợ
                </button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="fl-alert fl-alert-success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="fl-alert fl-alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Chưa thể lưu dữ liệu.</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @unless($schemaReady)
        <div class="fl-alert fl-alert-warning">
            <i class="bi bi-database-exclamation"></i>
            <div>
                <strong>Module đã có giao diện nhưng chưa có bảng dữ liệu.</strong>
                <div>Chạy <code>php artisan migrate --force</code> sau khi cập nhật source.</div>
            </div>
        </div>
    @endunless

    <div class="fl-kpis">
        <div class="fl-kpi blue">
            <span class="fl-kpi__icon"><i class="bi bi-receipt"></i></span>
            <div><small>TỔNG CÔNG NỢ</small><strong>{{ $money($summary['total_amount'] ?? 0) }}</strong><span>{{ number_format($summary['count'] ?? 0) }} khoản</span></div>
        </div>
        <div class="fl-kpi green">
            <span class="fl-kpi__icon"><i class="bi bi-check2-circle"></i></span>
            <div><small>ĐÃ THANH TOÁN</small><strong>{{ $money($summary['paid_amount'] ?? 0) }}</strong><span>Đã ghi nhận</span></div>
        </div>
        <div class="fl-kpi red">
            <span class="fl-kpi__icon"><i class="bi bi-hourglass-split"></i></span>
            <div><small>CÒN PHẢI TRẢ</small><strong>{{ $money($summary['remain_amount'] ?? 0) }}</strong><span>Số dư hiện tại</span></div>
        </div>
        <div class="fl-kpi amber">
            <span class="fl-kpi__icon"><i class="bi bi-alarm"></i></span>
            <div><small>QUÁ HẠN</small><strong>{{ $money($summary['overdue_amount'] ?? 0) }}</strong><span>Cần ưu tiên xử lý</span></div>
        </div>
    </div>

    <div class="fl-panel">
        <div class="fl-panel__head">
            <div>
                <h2>Bộ lọc & tìm kiếm</h2>
                <p>Thu hẹp danh sách theo đối tượng, chứng từ, trạng thái và thời gian.</p>
            </div>
            @if(collect($filters)->filter()->isNotEmpty())
                <a class="fl-reset" href="{{ route('finance.ledger.index', ['direction' => $direction, 'category' => $category]) }}"><i class="bi bi-arrow-counterclockwise"></i> Đặt lại</a>
            @endif
        </div>
        <form method="GET" class="fl-filter">
            <label class="fl-field fl-field-wide">
                <span>Tìm kiếm</span>
                <div class="fl-input-icon"><i class="bi bi-search"></i><input name="keyword" value="{{ $filters['keyword'] }}" placeholder="Đối tượng / công ty / số chứng từ / ghi chú..."></div>
            </label>
            <label class="fl-field">
                <span>Trạng thái</span>
                <select name="status">
                    <option value="">Tất cả</option>
                    @foreach($statusMap as $value => [$label])
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="fl-field">
                <span>Từ ngày</span>
                <input type="date" name="from_date" value="{{ $filters['from_date'] }}">
            </label>
            <label class="fl-field">
                <span>Đến ngày</span>
                <input type="date" name="to_date" value="{{ $filters['to_date'] }}">
            </label>
            <button class="fl-btn fl-btn-primary fl-filter-btn" type="submit"><i class="bi bi-funnel"></i> Lọc dữ liệu</button>
        </form>
    </div>

    <div class="fl-panel fl-table-panel">
        <div class="fl-panel__head">
            <div>
                <h2>Danh sách {{ mb_strtolower($config['title']) }}</h2>
                <p>Bấm “Chi tiết” để xem lịch sử, ghi nhận thanh toán hoặc chỉnh sửa khoản công nợ.</p>
            </div>
        </div>

        @if($schemaReady)
            <div class="table-responsive">
                <table class="fl-table">
                    <thead>
                    <tr>
                        <th>Đối tượng</th>
                        <th>Chứng từ</th>
                        <th>Ngày / Hạn</th>
                        <th class="tw:text-right">Tổng công nợ</th>
                        <th class="tw:text-right">Đã trả</th>
                        <th class="tw:text-right">Còn lại</th>
                        <th>Trạng thái</th>
                        <th class="tw:text-right">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($entries as $entry)
                        @php
                            $remain = max((float)$entry->total_amount - (float)$entry->paid_amount, 0);
                            [$statusText, $statusClass] = $statusMap[$entry->status] ?? [$entry->status, 'neutral'];
                            $txs = $transactionsByEntry->get($entry->id, collect());
                        @endphp
                        <tr>
                            <td>
                                <div class="fl-party"><strong>{{ $entry->counterparty_name }}</strong>@if($entry->company_name)<span>{{ $entry->company_name }}</span>@endif</div>
                            </td>
                            <td>
                                <div class="fl-doc"><strong>{{ $entry->reference_no ?: '—' }}</strong>@if($entry->reference_date)<span>{{ \Carbon\Carbon::parse($entry->reference_date)->format('d/m/Y') }}</span>@endif</div>
                            </td>
                            <td>
                                <div class="fl-dates">
                                    <span><i class="bi bi-calendar3"></i>{{ $entry->reference_date ? \Carbon\Carbon::parse($entry->reference_date)->format('d/m/Y') : '—' }}</span>
                                    <span class="{{ $entry->status === 'overdue' ? 'is-overdue' : '' }}"><i class="bi bi-alarm"></i>{{ $entry->due_date ? \Carbon\Carbon::parse($entry->due_date)->format('d/m/Y') : 'Không đặt hạn' }}</span>
                                </div>
                            </td>
                            <td class="tw:text-right fl-money">{{ $money($entry->total_amount) }}</td>
                            <td class="tw:text-right fl-money fl-money-success">{{ $money($entry->paid_amount) }}</td>
                            <td class="tw:text-right fl-money fl-money-danger">{{ $money($remain) }}</td>
                            <td><span class="fl-status {{ $statusClass }}"><i></i>{{ $statusText }}</span></td>
                            <td class="tw:text-right">
                                <button type="button" class="fl-detail-btn" onclick="toggleLedgerRow({{ $entry->id }})"><i class="bi bi-chevron-down"></i> Chi tiết</button>
                            </td>
                        </tr>
                        <tr id="ledger-detail-{{ $entry->id }}" class="fl-detail-row" hidden>
                            <td colspan="8">
                                <div class="fl-detail-grid">
                                    <section class="fl-detail-card">
                                        <div class="fl-detail-title"><i class="bi bi-cash-coin"></i><div><strong>Ghi nhận thanh toán</strong><small>Còn lại {{ $money($remain) }}</small></div></div>
                                        @if($remain > 0)
                                            <form method="POST" action="{{ route('finance.ledger.transactions.store', ['direction' => $direction, 'category' => $category, 'entry' => $entry->id]) }}" class="fl-mini-form">
                                                @csrf
                                                <label><span>Số tiền</span><input class="money-input" name="amount" inputmode="numeric" placeholder="0" required></label>
                                                <label><span>Ngày thanh toán</span><input type="date" name="transaction_date" value="{{ now()->toDateString() }}" required></label>
                                                <label><span>Hình thức</span><select name="method"><option value="bank">Chuyển khoản</option><option value="cash">Tiền mặt</option><option value="other">Khác</option></select></label>
                                                @if($accounts->count())
                                                    <label><span>Quỹ / tài khoản tham chiếu</span><select name="account_id"><option value="">Không chọn</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}{{ $account->code ? ' · '.$account->code : '' }}</option>@endforeach</select></label>
                                                @endif
                                                <label><span>Số phiếu / UNC</span><input name="reference_no" placeholder="VD: UNC-0826-001"></label>
                                                <label class="span-2"><span>Ghi chú</span><input name="note" placeholder="Nội dung thanh toán..."></label>
                                                <div class="span-2"><button class="fl-btn fl-btn-success" type="submit"><i class="bi bi-check2-circle"></i> Ghi nhận chi</button></div>
                                            </form>
                                        @else
                                            <div class="fl-paid-note"><i class="bi bi-check-circle-fill"></i> Khoản công nợ này đã thanh toán đủ.</div>
                                        @endif
                                    </section>

                                    <section class="fl-detail-card">
                                        <div class="fl-detail-title"><i class="bi bi-clock-history"></i><div><strong>Lịch sử thanh toán</strong><small>{{ $txs->count() }} lần ghi nhận</small></div></div>
                                        <div class="fl-history">
                                            @forelse($txs as $tx)
                                                <div class="fl-history-item">
                                                    <div>
                                                        <strong>{{ $money($tx->amount) }}</strong>
                                                        <span>{{ \Carbon\Carbon::parse($tx->transaction_date)->format('d/m/Y') }} · {{ $methodMap[$tx->method] ?? $tx->method }}@if($tx->account_name) · {{ $tx->account_name }}@endif</span>
                                                        @if($tx->reference_no || $tx->note)<small>{{ $tx->reference_no }}{{ $tx->reference_no && $tx->note ? ' · ' : '' }}{{ $tx->note }}</small>@endif
                                                    </div>
                                                    <form method="POST" action="{{ route('finance.ledger.transactions.destroy', ['direction' => $direction, 'category' => $category, 'entry' => $entry->id, 'transaction' => $tx->id]) }}" onsubmit="return confirm('Xóa lần ghi nhận thanh toán này?')">
                                                        @csrf @method('DELETE')
                                                        <button class="fl-icon-btn danger" title="Xóa"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            @empty
                                                <div class="fl-empty-small">Chưa có lần thanh toán nào.</div>
                                            @endforelse
                                        </div>
                                    </section>

                                    <section class="fl-detail-card fl-detail-card-wide">
                                        <div class="fl-detail-title"><i class="bi bi-pencil-square"></i><div><strong>Thông tin công nợ</strong><small>Chỉnh chứng từ, kỳ hạn và giá trị khoản nợ.</small></div></div>
                                        <form method="POST" action="{{ route('finance.ledger.update', ['direction' => $direction, 'category' => $category, 'entry' => $entry->id]) }}" class="fl-edit-grid">
                                            @csrf @method('PUT')
                                            <label><span>{{ $config['counterparty'] }}</span><input name="counterparty_name" value="{{ $entry->counterparty_name }}" required></label>
                                            <label><span>Công ty / đơn vị</span><input name="company_name" value="{{ $entry->company_name }}"></label>
                                            <label><span>Số chứng từ / hợp đồng</span><input name="reference_no" value="{{ $entry->reference_no }}"></label>
                                            <label><span>Ngày chứng từ</span><input type="date" name="reference_date" value="{{ $entry->reference_date }}"></label>
                                            <label><span>Hạn thanh toán</span><input type="date" name="due_date" value="{{ $entry->due_date }}"></label>
                                            <label><span>Tổng công nợ</span><input class="money-input" name="total_amount" value="{{ number_format((float)$entry->total_amount, 0, ',', '.') }}" required></label>
                                            <label class="span-2"><span>Thông tin ngân hàng</span><textarea name="bank_info" rows="2">{{ $entry->bank_info }}</textarea></label>
                                            <label class="span-2"><span>Ghi chú</span><textarea name="note" rows="2">{{ $entry->note }}</textarea></label>
                                            <div class="span-2 fl-edit-actions">
                                                <button class="fl-btn fl-btn-primary" type="submit"><i class="bi bi-save"></i> Lưu cập nhật</button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('finance.ledger.destroy', ['direction' => $direction, 'category' => $category, 'entry' => $entry->id]) }}" onsubmit="return confirm('Xóa toàn bộ khoản công nợ và lịch sử thanh toán?')" class="fl-delete-entry">
                                            @csrf @method('DELETE')
                                            <button class="fl-btn fl-btn-danger" type="submit"><i class="bi bi-trash"></i> Xóa khoản công nợ</button>
                                        </form>
                                    </section>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="fl-empty"><i class="bi bi-inbox"></i><strong>Chưa có dữ liệu</strong><span>Thêm khoản công nợ đầu tiên để bắt đầu theo dõi.</span></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($entries, 'links'))
                <div class="fl-pagination">{{ $entries->links() }}</div>
            @endif
        @endif
    </div>

    @if($schemaReady)
        <details id="fl-create" class="fl-create-drawer" @if($errors->any()) open @endif>
            <summary><span><i class="bi bi-plus-circle"></i> Thêm {{ mb_strtolower($config['title']) }}</span><i class="bi bi-x-lg"></i></summary>
            <div class="fl-create-body">
                <div class="fl-create-title"><strong>Tạo khoản công nợ mới</strong><span>Nhập chứng từ gốc, tổng nghĩa vụ và kỳ hạn thanh toán.</span></div>
                <form method="POST" action="{{ route('finance.ledger.store', ['direction' => $direction, 'category' => $category]) }}" class="fl-create-grid">
                    @csrf
                    <label><span>{{ $config['counterparty'] }} *</span><input name="counterparty_name" value="{{ old('counterparty_name') }}" required></label>
                    <label><span>Công ty / đơn vị</span><input name="company_name" value="{{ old('company_name') }}"></label>
                    <label><span>Số chứng từ / hợp đồng</span><input name="reference_no" value="{{ old('reference_no') }}"></label>
                    <label><span>Ngày chứng từ</span><input type="date" name="reference_date" value="{{ old('reference_date', now()->toDateString()) }}"></label>
                    <label><span>Hạn thanh toán</span><input type="date" name="due_date" value="{{ old('due_date') }}"></label>
                    <label><span>Tổng công nợ *</span><input class="money-input" name="total_amount" value="{{ old('total_amount') }}" inputmode="numeric" placeholder="0" required></label>
                    <label><span>Đã thanh toán ban đầu</span><input class="money-input" name="paid_amount" value="{{ old('paid_amount', 0) }}" inputmode="numeric" placeholder="0"></label>
                    <label class="span-2"><span>Thông tin ngân hàng</span><textarea name="bank_info" rows="3" placeholder="Ngân hàng, STK, chủ tài khoản...">{{ old('bank_info') }}</textarea></label>
                    <label class="span-2"><span>Ghi chú</span><textarea name="note" rows="3" placeholder="Nội dung khoản phải trả...">{{ old('note') }}</textarea></label>
                    <div class="span-2 fl-create-actions"><button class="fl-btn fl-btn-primary" type="submit"><i class="bi bi-check-lg"></i> Lưu công nợ</button></div>
                </form>
            </div>
        </details>
    @endif
</div>

<style>
.finance-ledger-page{--fl-navy:#0f2740;--fl-text:#10243b;--fl-muted:#66788c;--fl-border:#dfe9f2;--fl-bg:#f5f9fc;--fl-cyan:#06a9c7;--fl-green:#159a72;--fl-red:#d94b67;--fl-amber:#d9921e;padding:24px 26px 42px;background:linear-gradient(180deg,#f4fafc 0,#f8fafc 46%,#fff 100%);min-height:100%;color:var(--fl-text)}
.fl-hero{display:flex;justify-content:space-between;gap:24px;align-items:center;padding:25px 28px;border:1px solid #dbeaf1;border-radius:24px;background:linear-gradient(135deg,#fff 0%,#f4fbfd 70%,#eef9fb 100%);box-shadow:0 16px 42px rgba(16,36,59,.07);margin-bottom:18px}.fl-kicker{font-size:11px;font-weight:900;letter-spacing:.1em;color:#0b9ab5;margin-bottom:7px}.fl-hero h1{display:flex;align-items:center;gap:11px;font-size:28px;line-height:1.2;font-weight:950;letter-spacing:-.035em;margin:0 0 7px}.fl-hero h1 i{width:43px;height:43px;border-radius:14px;display:grid;place-items:center;background:#e8f8fb;color:#079bb6;font-size:21px}.fl-hero p{margin:0;color:var(--fl-muted);max-width:760px}.fl-hero__actions{display:flex;gap:10px;flex-wrap:wrap}.fl-btn{border:0;border-radius:12px;min-height:42px;padding:0 15px;display:inline-flex;align-items:center;justify-content:center;gap:7px;font-weight:850;text-decoration:none;cursor:pointer;white-space:nowrap}.fl-btn-primary{background:linear-gradient(135deg,#0aa6c1,#087f9d);color:#fff;box-shadow:0 12px 28px rgba(8,127,157,.19)}.fl-btn-light{background:#fff;color:#31506b;border:1px solid var(--fl-border)}.fl-btn-success{background:#0f9a70;color:#fff}.fl-btn-danger{background:#fff1f3;color:#c92c50;border:1px solid #ffd0d8}.fl-alert{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;border-radius:14px;margin-bottom:14px;font-size:14px}.fl-alert ul{margin:6px 0 0;padding-left:18px}.fl-alert-success{background:#eafaf4;color:#08785a;border:1px solid #c8f1df}.fl-alert-danger{background:#fff1f3;color:#b32849;border:1px solid #ffd1da}.fl-alert-warning{background:#fff9e9;color:#8d650b;border:1px solid #f5dfa1}.fl-alert code{background:rgba(255,255,255,.65);padding:1px 5px;border-radius:5px;color:inherit}.fl-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0}.fl-kpi{display:flex;align-items:center;gap:13px;padding:18px;border:1px solid var(--fl-border);border-radius:19px;background:#fff;box-shadow:0 12px 32px rgba(16,36,59,.055)}.fl-kpi__icon{width:49px;height:49px;border-radius:15px;display:grid;place-items:center;font-size:21px;flex:0 0 auto}.fl-kpi.blue .fl-kpi__icon{background:#e8f7fb;color:#0799b4}.fl-kpi.green .fl-kpi__icon{background:#e8f8f1;color:#158b69}.fl-kpi.red .fl-kpi__icon{background:#fff0f3;color:#d84564}.fl-kpi.amber .fl-kpi__icon{background:#fff6e7;color:#d38a16}.fl-kpi small{display:block;color:#718296;font-weight:900;font-size:10px;letter-spacing:.05em}.fl-kpi strong{display:block;font-size:20px;line-height:1.25;letter-spacing:-.03em;margin:3px 0}.fl-kpi span{font-size:12px;color:#8291a2}.fl-panel{background:#fff;border:1px solid var(--fl-border);border-radius:20px;box-shadow:0 14px 38px rgba(16,36,59,.055);margin-top:15px;overflow:hidden}.fl-panel__head{padding:18px 20px;border-bottom:1px solid #eaf0f5;display:flex;align-items:center;justify-content:space-between;gap:15px}.fl-panel__head h2{font-size:18px;font-weight:950;margin:0 0 3px;letter-spacing:-.02em}.fl-panel__head p{font-size:12.5px;color:var(--fl-muted);margin:0}.fl-reset{font-size:12px;font-weight:800;color:#15849a;text-decoration:none}.fl-filter{display:grid;grid-template-columns:minmax(250px,2fr) 1fr 1fr 1fr auto;gap:11px;padding:17px 20px;align-items:end}.fl-field{display:grid;gap:6px}.fl-field>span,.fl-mini-form label>span,.fl-edit-grid label>span,.fl-create-grid label>span{font-size:11px;font-weight:850;color:#607287}.fl-field input,.fl-field select,.fl-mini-form input,.fl-mini-form select,.fl-edit-grid input,.fl-edit-grid select,.fl-edit-grid textarea,.fl-create-grid input,.fl-create-grid select,.fl-create-grid textarea{width:100%;border:1px solid #dbe6ef;border-radius:11px;background:#fff;color:#18324d;outline:none;min-height:42px;padding:9px 11px;font-size:13px}.fl-field input:focus,.fl-field select:focus,.fl-mini-form input:focus,.fl-mini-form select:focus,.fl-edit-grid input:focus,.fl-edit-grid textarea:focus,.fl-create-grid input:focus,.fl-create-grid textarea:focus{border-color:#71cddd;box-shadow:0 0 0 3px rgba(6,169,199,.08)}.fl-input-icon{position:relative}.fl-input-icon i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#8ba0b3}.fl-input-icon input{padding-left:35px}.fl-filter-btn{height:42px}.fl-table-panel{overflow:visible}.table-responsive{overflow-x:auto}.fl-table{width:100%;border-collapse:collapse;min-width:1130px}.fl-table th{font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#65798d;background:#f9fbfd;padding:13px 15px;border-bottom:1px solid #e7eef4;white-space:nowrap}.fl-table td{padding:14px 15px;border-bottom:1px solid #edf2f6;vertical-align:middle;font-size:13px}.fl-party,.fl-doc,.fl-dates{display:grid;gap:3px}.fl-party strong,.fl-doc strong{font-weight:900;color:#18324d}.fl-party span,.fl-doc span,.fl-dates span{font-size:11.5px;color:#7a8d9f}.fl-dates span{display:flex;align-items:center;gap:5px}.fl-dates .is-overdue{color:#c62d50;font-weight:800}.fl-money{font-weight:900;color:#1d3953;white-space:nowrap}.fl-money-success{color:#138061}.fl-money-danger{color:#c73555}.fl-status{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;font-size:10.5px;font-weight:850;white-space:nowrap}.fl-status i{width:6px;height:6px;border-radius:50%;background:currentColor}.fl-status.danger{background:#fff1f3;color:#c73555}.fl-status.warning{background:#fff7e7;color:#b87409}.fl-status.success{background:#eaf9f3;color:#107b5d}.fl-status.overdue{background:#ffe9ee;color:#b91f45}.fl-status.neutral{background:#eef3f7;color:#607287}.fl-detail-btn{border:1px solid #dbe8ef;background:#f8fcfd;color:#217e92;border-radius:10px;padding:7px 10px;font-weight:850;font-size:11px}.fl-detail-row td{background:#f7fbfd;padding:16px 18px 20px}.fl-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.fl-detail-card{border:1px solid #dce8ef;border-radius:15px;background:#fff;padding:15px}.fl-detail-card-wide{grid-column:1/-1;position:relative}.fl-detail-title{display:flex;gap:9px;align-items:center;margin-bottom:12px}.fl-detail-title>i{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#e9f7fa;color:#0a91aa}.fl-detail-title strong{display:block;font-size:13px}.fl-detail-title small{display:block;color:#7c8d9f;font-size:10.5px;margin-top:2px}.fl-mini-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.fl-mini-form label,.fl-edit-grid label,.fl-create-grid label{display:grid;gap:5px}.span-2{grid-column:span 2}.fl-paid-note{padding:12px;border-radius:11px;background:#eaf9f3;color:#0f7d5f;font-weight:800}.fl-history{display:grid;gap:8px;max-height:300px;overflow:auto}.fl-history-item{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px;border:1px solid #e7eef3;border-radius:11px;background:#fbfdfe}.fl-history-item strong{display:block;font-size:13px;color:#0f7559}.fl-history-item span,.fl-history-item small{display:block;font-size:10.5px;color:#7b8d9e;margin-top:2px}.fl-icon-btn{border:0;width:30px;height:30px;border-radius:9px;background:#f1f5f8;color:#667a8d}.fl-icon-btn.danger{background:#fff0f3;color:#cc3152}.fl-empty-small{padding:18px;text-align:center;color:#8798a8;font-size:12px}.fl-edit-grid,.fl-create-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.fl-edit-actions,.fl-create-actions{display:flex;justify-content:flex-end}.fl-delete-entry{position:absolute;right:15px;bottom:15px}.fl-empty{padding:55px 20px;display:grid;place-items:center;text-align:center;color:#8092a3}.fl-empty i{font-size:34px;color:#8acbd7}.fl-empty strong{font-size:15px;margin-top:7px;color:#425c73}.fl-empty span{font-size:12px;margin-top:3px}.fl-pagination{padding:14px 18px}.fl-create-drawer{position:fixed;z-index:1055;right:22px;bottom:20px;width:min(700px,calc(100vw - 44px));max-height:calc(100vh - 40px);overflow:auto;border:1px solid #d8e6ee;border-radius:20px;background:#fff;box-shadow:0 30px 80px rgba(15,39,64,.25)}.fl-create-drawer:not([open]){width:auto;max-height:none;border:0;background:transparent;box-shadow:none}.fl-create-drawer:not([open]) summary{display:none}.fl-create-drawer summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 18px;border-bottom:1px solid #e7eef3;background:#f7fbfd;font-weight:900;cursor:pointer}.fl-create-drawer summary::-webkit-details-marker{display:none}.fl-create-body{padding:18px}.fl-create-title{display:grid;margin-bottom:13px}.fl-create-title strong{font-size:18px}.fl-create-title span{font-size:12px;color:#728598;margin-top:3px}.fl-create-grid textarea,.fl-edit-grid textarea{min-height:74px;resize:vertical}.fl-create-actions{margin-top:2px}
@media(max-width:1180px){.fl-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.fl-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.fl-field-wide{grid-column:span 2}.fl-filter-btn{width:100%}.fl-detail-grid{grid-template-columns:1fr}.fl-detail-card-wide{grid-column:auto}.fl-delete-entry{position:static;margin-top:10px}}
@media(max-width:720px){.finance-ledger-page{padding:14px}.fl-hero{padding:18px;align-items:flex-start;flex-direction:column}.fl-hero h1{font-size:22px}.fl-kpis{grid-template-columns:1fr}.fl-filter{grid-template-columns:1fr;padding:14px}.fl-field-wide{grid-column:auto}.fl-panel__head{align-items:flex-start}.fl-mini-form,.fl-edit-grid,.fl-create-grid{grid-template-columns:1fr}.span-2{grid-column:auto}.fl-create-drawer{right:10px;bottom:10px;width:calc(100vw - 20px);max-height:calc(100vh - 20px)}}
</style>

<script>
function toggleLedgerRow(id){
    const row=document.getElementById('ledger-detail-'+id);
    if(!row) return;
    row.hidden=!row.hidden;
}

document.addEventListener('input',function(e){
    if(!e.target.classList.contains('money-input')) return;
    const raw=e.target.value.replace(/\D/g,'');
    e.target.value=raw ? Number(raw).toLocaleString('vi-VN') : '';
});
</script>
@endsection
