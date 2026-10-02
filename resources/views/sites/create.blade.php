{{-- $egoSiteCompanyOptions do EgoDefaultCompany cấp qua view composer. Trước đây
     cùng một khối truy vấn companies được chép vào cả ba view sites/index,
     sites/create, sites/edit — sửa điều kiện lọc ở một chỗ mà quên hai chỗ kia
     là ra ba danh sách khác nhau trên ba trang. --}}
@extends('layouts.app')

@section('content')
@php
    /* EGO_PROJECT_CREATE_META_START */
    $projectType = $projectType ?? request()->route('project_type');
    $projectLabel = $projectLabel ?? 'công trình';
    $pageTitle = $pageTitle ?? 'Tạo công trình';
    $pageSubtitle = $pageSubtitle ?? 'Tạo nhanh công trình.';
    $projectIndexUrl = $projectIndexUrl ?? url('/cong-trinh');
    $projectStoreUrl = $projectStoreUrl ?? url('/cong-trinh');
    /* EGO_PROJECT_CREATE_META_END */

    /* EGO_SITE_COMPANY_CREATE_DATA_START */
    $egoOldSiteCompanyId = old('company_id', session('ego_company_id', ''));
    /* EGO_SITE_COMPANY_CREATE_DATA_END */

    $oldTerms = old('payment_terms');

    if (!$oldTerms || !is_array($oldTerms) || count($oldTerms) === 0) {
        $oldTerms = [
            ['name' => 'Đợt 1 - Đặt cọc', 'percent' => 30, 'amount' => '', 'due_date' => '', 'note' => ''],
            ['name' => 'Đợt 2 - Triển khai', 'percent' => 40, 'amount' => '', 'due_date' => '', 'note' => ''],
            ['name' => 'Đợt 3 - Nghiệm thu / bàn giao', 'percent' => 30, 'amount' => '', 'due_date' => '', 'note' => ''],
        ];
    }
@endphp

<div class="container-fluid tw:px-6 tw:py-4 ego-sites-form">
    <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-2 tw:mb-4 ego-header">
        <div>
            <div class="tw:flex tw:items-center tw:gap-2 tw:mb-1">
                <span class="page-icon"><i class="bi bi-buildings"></i></span>
                <h4 class="tw:font-bold tw:mb-0">{{ $pageTitle }}</h4>
            </div>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">
                {{ $pageSubtitle }}
            </div>
        </div>

        <x-ui.button variant="outline-secondary" href="{{ $projectIndexUrl }}">
            <i class="bi bi-arrow-left"></i> Quay lại
        </x-ui.button>
    </div>

    @if ($errors->any())
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]" style="border-radius:16px;">
            <div class="tw:font-semibold tw:mb-1">
                <i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:
            </div>
            <ul class="tw:mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ $projectStoreUrl }}" id="siteCreateForm">
        @csrf
        <input type="hidden" name="project_type" value="{{ old('project_type', $projectType) }}">

        <div class="row g-3">
            <div class="col-lg-8">

                {{-- THÔNG TIN CÔNG TRÌNH --}}
                <x-ui.card class="border-0 shadow-ego ego-card">
                    <x-ui.card-header class="bg-white border-0 tw:py-4">
                        <div class="tw:flex tw:items-center tw:gap-2">
                            <span class="icon-pill"><i class="bi bi-buildings"></i></span>
                            <div>
                                <div class="tw:font-bold">Thông tin công trình</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Các trường có dấu * là bắt buộc.</div>
                            </div>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="row g-3">

                            {{-- EGO_SITE_COMPANY_CREATE_FIELD_START --}}
                            <div class="col-md-4">
                                <x-ui.label>Công ty <span class="text-danger">*</span></x-ui.label>
                                <x-ui.select name="company_id" class="sc-input" required>
                                    <option value="">-- Chọn công ty --</option>
                                    @foreach($egoSiteCompanyOptions as $company)
                                        <option value="{{ $company->id }}" {{ (string)$egoOldSiteCompanyId === (string)$company->id ? 'selected' : '' }}>
                                            {{ trim(($company->code ?? '') . ' - ' . ($company->name ?? '')) }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                            {{-- EGO_SITE_COMPANY_CREATE_FIELD_END --}}

                            <div class="col-md-5">
                                <x-ui.label>Tên công trình <span class="text-danger">*</span></x-ui.label>
                                <x-ui.input name="name" class="sc-input" required value="{{ old('name') }}"
                                       placeholder="VD: Công trình nhà Anh A" />
                            </div>

                            <div class="col-md-3">
                                <x-ui.label>Trạng thái</x-ui.label>
                                <x-ui.select name="status" class="sc-input">
                                    <option value="" {{ old('status')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="planning" {{ old('status')=='planning'?'selected':'' }}>Chuẩn bị</option>
                                    <option value="installing" {{ old('status')=='installing'?'selected':'' }}>Đang triển khai</option>
                                    <option value="done" {{ old('status')=='done'?'selected':'' }}>Đã hoàn thành</option>
                                    <option value="warranty" {{ old('status')=='warranty'?'selected':'' }}>Đang bảo hành</option>
                                </x-ui.select>
                            </div>

                            <div class="col-md-12">
                                <x-ui.label>Địa chỉ <span class="text-danger">*</span></x-ui.label>
                                <x-ui.input name="address" class="sc-input" value="{{ old('address') }}"
                                       placeholder="VD: 123 Lê Văn Lương, TP.HCM" required />
                            </div>

                            {{-- Hai trường dưới đây là thứ bảng điều phối dự án cần để xếp việc.
                                 Trước khi có chúng, công trình tạo từ trang này vào bảng dự án mà
                                 không có người phụ trách: đo 2026-09-04 thấy 13/19 công trình
                                 thiếu `lead_engineer_id`. --}}
                            <div class="col-md-6">
                                <x-ui.label>Kỹ sư phụ trách <span class="text-danger">*</span></x-ui.label>
                                <x-ui.select name="lead_engineer_id" class="sc-input" required>
                                    <option value="">— Chọn kỹ sư phụ trách —</option>
                                    @foreach ($engineers as $engineer)
                                        <option value="{{ $engineer->id }}"
                                            @selected((string) old('lead_engineer_id') === (string) $engineer->id)>
                                            {{ $engineer->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Mức ưu tiên <span class="text-danger">*</span></x-ui.label>
                                <x-ui.select name="priority" class="sc-input" required>
                                    @foreach ($priorities as $value => $label)
                                        <option value="{{ $value }}"
                                            @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Người liên hệ</x-ui.label>
                                <x-ui.input name="contact_name" class="sc-input" value="{{ old('contact_name') }}"
                                       placeholder="VD: Anh A" />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>SĐT liên hệ</x-ui.label>
                                <x-ui.input name="contact_phone" class="sc-input" value="{{ old('contact_phone') }}"
                                       placeholder="VD: 0909xxxxxx" />
                            </div>

                            <div class="col-md-12">
                                <x-ui.label>Ghi chú</x-ui.label>
                                <x-ui.input as="textarea" name="note" class="sc-input" rows="3"
                                          placeholder="Ghi chú nội bộ...">{{ old('note') }}</x-ui.input>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>

                {{-- TÀI CHÍNH --}}
                <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4 finance-card">
                    <x-ui.card-header class="bg-white border-0 tw:py-4">
                        <div class="tw:flex tw:items-center tw:gap-2">
                            <span class="icon-pill finance-icon"><i class="bi bi-cash-coin"></i></span>
                            <div>
                                <div class="tw:font-bold">Tài chính công trình</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Doanh thu dự án và các đợt thanh toán.</div>
                            </div>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="finance-hero tw:mb-4">
                            <div class="row g-3 tw:items-end">
                                <div class="col-md-5">
                                    <x-ui.label>Giá trị hợp đồng / Doanh thu dự án</x-ui.label>
                                    <div class="input-money">
                                        <x-ui.input name="contract_amount" id="contract_amount"
                                               type="number" step="any" min="0"
                                               class="sc-input tw:text-right tw:font-bold"
                                               value="{{ old('contract_amount', 0) }}"
                                               placeholder="VD: 150000000" />
                                        <span>đ</span>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <x-ui.label>Ngày ký hợp đồng</x-ui.label>
                                    <x-ui.input name="contract_signed_at" type="date" class="sc-input"
                                           value="{{ old('contract_signed_at') }}" />
                                </div>

                                <div class="col-md-4">
                                    <x-ui.label>Ghi chú tài chính</x-ui.label>
                                    <x-ui.input name="finance_note" class="sc-input"
                                           value="{{ old('finance_note') }}"
                                           placeholder="VD: cọc 30%, nghiệm thu 70%" />
                                </div>
                            </div>
                        </div>


                        <div class="tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2 tw:mb-2">
                            <div>
                                <div class="tw:font-bold">Các đợt thanh toán</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Nhập % hoặc số tiền. Nếu có %, hệ thống tự tính.</div>
                            </div>

                            <x-ui.button variant="none" size="none" class="btn-outline-ego tw:py-[6px] tw:px-3" type="button" id="btnAddPaymentTerm">
                                <i class="bi bi-plus-lg"></i> Thêm đợt
                            </x-ui.button>
                        </div>

                        <div class="table-responsive payment-terms-wrap">
                            <table class="table align-middle tw:mb-0 ego-table payment-terms-table">
                                <thead class="table-light">
                                <tr>
                                    <th style="width:54px" class="tw:text-center">#</th>
                                    <th style="min-width:230px">Tên đợt</th>
                                    <th style="width:120px" class="tw:text-right">%</th>
                                    <th style="width:190px" class="tw:text-right">Số tiền</th>
                                    <th style="width:170px">Ngày dự kiến</th>
                                    <th style="min-width:220px">Ghi chú</th>
                                    <th style="width:80px" class="tw:text-right">Xóa</th>
                                </tr>
                                </thead>

                                <tbody id="paymentTermsTbody">
                                @foreach($oldTerms as $i => $term)
                                    <tr class="payment-term-row">
                                        <td class="tw:text-center term-idx">{{ $i + 1 }}</td>

                                        <td>
                                            <x-ui.input name="payment_terms[{{ $i }}][name]" class="sc-input"
                                                   value="{{ $term['name'] ?? '' }}" />
                                        </td>

                                        <td>
                                            <x-ui.input name="payment_terms[{{ $i }}][percent]" type="number"
                                                   min="0" max="100" step="any"
                                                   class="sc-input tw:text-right term-percent"
                                                   value="{{ $term['percent'] ?? '' }}" />
                                        </td>

                                        <td>
                                            <x-ui.input name="payment_terms[{{ $i }}][amount]" type="number"
                                                   min="0" step="any"
                                                   class="sc-input tw:text-right term-amount"
                                                   value="{{ $term['amount'] ?? '' }}" />
                                        </td>

                                        <td>
                                            <x-ui.input name="payment_terms[{{ $i }}][due_date]" type="date"
                                                   class="sc-input"
                                                   value="{{ $term['due_date'] ?? '' }}" />
                                        </td>

                                        <td>
                                            <x-ui.input name="payment_terms[{{ $i }}][note]" class="sc-input"
                                                   value="{{ $term['note'] ?? '' }}" />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.button variant="outline-danger" size="sm" class="btnRemoveTerm" type="button" :disabled="count($oldTerms) === 1">
                                                <i class="bi bi-trash"></i>
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="finance-mini-summary tw:mt-4">
                            <div class="mini-box">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng hợp đồng</div>
                                <div class="tw:font-bold" id="financeContractMini">0 đ</div>
                            </div>
                            <div class="mini-box">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng các đợt</div>
                                <div class="tw:font-bold" id="financeTermsMini">0 đ</div>
                            </div>
                            <div class="mini-box">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Chênh lệch</div>
                                <div class="tw:font-bold" id="financeDiffMini">0 đ</div>
                            </div>
                            <div class="mini-box">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Số đợt</div>
                                <div class="tw:font-bold" id="financeTermCountMini">{{ count($oldTerms) }}</div>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>


                {{-- CHI PHÍ PHÁT SINH CÔNG TRÌNH --}}
                <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4 site-cost-card">
                    <x-ui.card-header class="bg-white border-0 tw:py-4">
                        <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-2">
                            <div class="tw:flex tw:items-center tw:gap-2">
                                <span class="icon-pill finance-icon"><i class="bi bi-wallet2"></i></span>
                                <div>
                                    <div class="tw:font-bold">Chi phí phát sinh công trình</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Nhân công, vận chuyển và các khoản chi phí khác.</div>
                                </div>
                            </div>
                            <span class="badge bg-light tw:text-[#212529]! border rounded-pill tw:px-4 tw:py-2">
                                Tự cộng chi phí khác
                            </span>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="tw:p-4 rounded-4 border bg-white tw:h-full">
                                    <x-ui.label class="tw:font-semibold">Chi phí nhân công</x-ui.label>
                                    <div class="input-money">
                                        <x-ui.input name="labor_cost" type="number" step="any" min="0"
                                               class="sc-input tw:text-right tw:font-bold"
                                               value="{{ old('labor_cost', 0) }}"
                                               placeholder="VD: 5000000" />
                                        <span>đ</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="tw:p-4 rounded-4 border bg-white tw:h-full">
                                    <x-ui.label class="tw:font-semibold">Chi phí vận chuyển</x-ui.label>
                                    <div class="input-money">
                                        <x-ui.input name="transport_cost" type="number" step="any" min="0"
                                               class="sc-input tw:text-right tw:font-bold"
                                               value="{{ old('transport_cost', 0) }}"
                                               placeholder="VD: 1000000" />
                                        <span>đ</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="tw:p-4 rounded-4 border" style="background:linear-gradient(135deg, rgba(11,201,170,.07), #fff);">
                                    <div class="tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2 tw:mb-4">
                                        <div>
                                            <div class="tw:font-bold">Chi phí khác</div>
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Có thể thêm nhiều dòng, hệ thống tự cộng tổng.</div>
                                        </div>

                                        <x-ui.button variant="none" size="sm" class="btn-outline-ego" type="button" data-add-other-cost>
                                            <i class="bi bi-plus-lg"></i> Thêm dòng
                                        </x-ui.button>
                                    </div>

                                    <input type="hidden" name="other_cost" value="{{ old('other_cost', 0) }}" data-other-cost-hidden>
                                    <input type="hidden" name="other_cost_note" value="{{ old('other_cost_note') }}" data-other-cost-note-hidden>

                                    <div data-other-cost-rows>
                                        <div class="row g-2 tw:items-end other-cost-row tw:mb-2" data-other-cost-row>
                                            <div class="col-md-4">
                                                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Số tiền</x-ui.label>
                                                <div class="input-money">
                                                    <x-ui.input type="number" step="any" min="0"
                                                           class="sc-input tw:text-right"
                                                           value="{{ old('other_cost', 0) }}"
                                                           placeholder="VD: 2000000"
                                                           data-other-cost-amount />
                                                    <span>đ</span>
                                                </div>
                                            </div>

                                            <div class="col-md-7">
                                                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Nội dung</x-ui.label>
                                                <x-ui.input type="text" class="sc-input"
                                                       value="{{ old('other_cost_note') }}"
                                                       placeholder="VD: Phụ kiện, ăn ở, bốc xếp..."
                                                       data-other-cost-note />
                                            </div>

                                            <div class="col-md-1 d-grid">
                                                <x-ui.button variant="outline-danger" type="button" data-remove-other-cost title="Xóa dòng">
                                                    <i class="bi bi-trash"></i>
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tw:flex tw:justify-end tw:mt-4">
                                        <div class="tw:px-4 tw:py-2 rounded-4 bg-white border">
                                            <span class="tw:text-[rgba(33,37,41,0.75)]! small">Tổng chi phí khác:</span>
                                            <span class="tw:font-bold tw:ml-2" data-other-cost-total>0 đ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-ui.card-body>

                    <script>
                    (function(){
                        function money(n){
                            n = Number(n || 0);
                            return n.toLocaleString('vi-VN') + ' đ';
                        }

                        document.querySelectorAll('.site-cost-card').forEach(function(card){
                            const rowsWrap = card.querySelector('[data-other-cost-rows]');
                            const addBtn = card.querySelector('[data-add-other-cost]');
                            const hiddenAmount = card.querySelector('[data-other-cost-hidden]');
                            const hiddenNote = card.querySelector('[data-other-cost-note-hidden]');
                            const totalEl = card.querySelector('[data-other-cost-total]');

                            if (!rowsWrap || !hiddenAmount || !hiddenNote) return;

                            function rows(){
                                return Array.from(rowsWrap.querySelectorAll('[data-other-cost-row]'));
                            }

                            function createRow(){
                                const row = document.createElement('div');
                                row.className = 'row g-2 align-items-end other-cost-row mb-2';
                                row.setAttribute('data-other-cost-row', '');

                                row.innerHTML = `
                                    <div class="col-md-4">
                                        <div class="input-money">
                                            <input type="number" step="any" min="0"
                                                   class="form-control tw:text-right"
                                                   placeholder="Số tiền"
                                                   data-other-cost-amount>
                                            <span>đ</span>
                                        </div>
                                    </div>

                                    <div class="col-md-7">
                                        <input type="text" class="form-control"
                                               placeholder="Nội dung chi phí"
                                               data-other-cost-note>
                                    </div>

                                    <div class="col-md-1 d-grid">
                                        <x-ui.button variant="outline-danger" type="button" data-remove-other-cost title="Xóa dòng">
                                            <i class="bi bi-trash"></i>
                                        </x-ui.button>
                                    </div>
                                `;

                                return row;
                            }

                            function sync(){
                                let total = 0;
                                const notes = [];

                                rows().forEach(function(row, index){
                                    const amountInput = row.querySelector('[data-other-cost-amount]');
                                    const noteInput = row.querySelector('[data-other-cost-note]');

                                    const amount = Number(amountInput?.value || 0);
                                    const note = (noteInput?.value || '').trim();

                                    total += amount;

                                    if (amount > 0 || note) {
                                        notes.push((index + 1) + '. ' + (note || 'Chi phí khác') + ' - ' + money(amount));
                                    }
                                });

                                hiddenAmount.value = Math.round(total);
                                hiddenNote.value = notes.join('\n');

                                if (totalEl) totalEl.textContent = money(total);

                                const currentRows = rows();
                                currentRows.forEach(function(row){
                                    const btn = row.querySelector('[data-remove-other-cost]');
                                    if (btn) btn.disabled = currentRows.length <= 1;
                                });
                            }

                            addBtn?.addEventListener('click', function(){
                                rowsWrap.appendChild(createRow());
                                sync();
                            });

                            rowsWrap.addEventListener('click', function(e){
                                const btn = e.target.closest('[data-remove-other-cost]');
                                if (!btn) return;

                                const row = btn.closest('[data-other-cost-row]');
                                if (row && rows().length > 1) {
                                    row.remove();
                                }

                                sync();
                            });

                            rowsWrap.addEventListener('input', sync);
                            sync();
                        });
                    })();
                    </script>
                </x-ui.card>


                {{-- HỆ THỐNG --}}
                <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4">
                    <x-ui.card-header class="bg-white border-0 tw:py-4">
                        <div class="tw:flex tw:items-center tw:gap-2">
                            <span class="icon-pill"><i class="bi bi-lightning-charge"></i></span>
                            <div>
                                <div class="tw:font-bold">Thông tin hệ thống</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                    Nhập inverter, pin lưu trữ, tấm pin. Hệ kWp sẽ tự tính từ số tấm × W/tấm.
                                </div>
                            </div>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="system-box tw:mb-4">
                            <div class="row g-3 tw:items-end">
                                <div class="col-md-4">
                                    <x-ui.label>Hiệu tấm pin</x-ui.label>
                                    <x-ui.input name="panel_brand" class="sc-input"
                                           value="{{ old('panel_brand') }}"
                                           placeholder="VD: Jinko / Longi / AE..." />
                                </div>

                                <div class="col-md-3">
                                    <x-ui.label>Công suất tấm pin (W/tấm)</x-ui.label>
                                    <x-ui.input name="solar_panel_wp" id="solar_panel_wp"
                                           type="number" min="0" step="1"
                                           class="sc-input tw:text-right"
                                           value="{{ old('solar_panel_wp') }}"
                                           placeholder="VD: 550" />
                                </div>

                                <div class="col-md-2">
                                    <x-ui.label>Số lượng tấm</x-ui.label>
                                    <x-ui.input name="solar_panel_qty" id="solar_panel_qty"
                                           type="number" min="0" step="1"
                                           class="sc-input tw:text-right"
                                           value="{{ old('solar_panel_qty') }}"
                                           placeholder="VD: 12" />
                                </div>

                                <div class="col-md-3">
                                    <x-ui.label>Hệ bao nhiêu kWp</x-ui.label>
                                    <x-ui.input name="system_kwp" id="system_kwp"
                                           type="number" min="0" step="any"
                                           class="sc-input tw:text-right tw:font-bold"
                                           value="{{ old('system_kwp') }}"
                                           placeholder="Tự tính" />
                                    <div class="form-text">= W/tấm × số tấm / 1000</div>
                                </div>
                            </div>
                        </div>

                        <div class="system-box tw:mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.label>Hiệu Inverter</x-ui.label>
                                    <x-ui.input name="inverter_brand" class="sc-input"
                                           value="{{ old('inverter_brand') }}"
                                           placeholder="VD: Goodwe / Solis / Sungrow..." />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.label>Công suất Inverter (kW)</x-ui.label>
                                    <x-ui.input name="system_kw_ac" type="number" step="any" min="0"
                                           class="sc-input tw:text-right"
                                           value="{{ old('system_kw_ac') }}"
                                           placeholder="VD: 5" />
                                </div>
                            </div>
                        </div>

                        <div class="system-box tw:mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.label>Hiệu pin lưu trữ</x-ui.label>
                                    <x-ui.input name="battery_brand" class="sc-input"
                                           value="{{ old('battery_brand') }}"
                                           placeholder="VD: Goodwe / Dyness / Pylontech..." />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.label>Pin lưu trữ bao nhiêu kWh</x-ui.label>
                                    <x-ui.input name="battery_kwh" type="number" step="any" min="0"
                                           class="sc-input tw:text-right"
                                           value="{{ old('battery_kwh') }}"
                                           placeholder="VD: 10.24" />
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <x-ui.label>Loại hệ</x-ui.label>
                                <x-ui.select name="system_type" class="sc-input">
                                    <option value="" {{ old('system_type')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="on_grid"  {{ old('system_type')=='on_grid'?'selected':'' }}>On-grid</option>
                                    <option value="hybrid"   {{ old('system_type')=='hybrid'?'selected':'' }}>Hybrid</option>
                                    <option value="off_grid" {{ old('system_type')=='off_grid'?'selected':'' }}>Off-grid</option>
                                    <option value="other" {{ old('system_type')=='other'?'selected':'' }}>Khác</option>
                                </x-ui.select>
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Điện áp</x-ui.label>
                                <x-ui.select name="phase" class="sc-input">
                                    <option value="" {{ old('phase')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="1_phase" {{ old('phase')=='1_phase'?'selected':'' }}>1 pha</option>
                                    <option value="3_phase" {{ old('phase')=='3_phase'?'selected':'' }}>3 pha</option>
                                </x-ui.select>
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Giai đoạn</x-ui.label>
                                <x-ui.select name="stage" class="sc-input">
                                    <option value="" {{ old('stage')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="survey" {{ old('stage')=='survey'?'selected':'' }}>Khảo sát</option>
                                    <option value="design" {{ old('stage')=='design'?'selected':'' }}>Thiết kế</option>
                                    <option value="installation" {{ old('stage')=='installation'?'selected':'' }}>Thi công</option>
                                    <option value="acceptance" {{ old('stage')=='acceptance'?'selected':'' }}>Nghiệm thu</option>
                                    <option value="operation" {{ old('stage')=='operation'?'selected':'' }}>Vận hành</option>
                                </x-ui.select>
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Ngày bắt đầu triển khai</x-ui.label>
                                <x-ui.input name="deployment_started_at" type="date" class="sc-input"
                                       value="{{ old('deployment_started_at') }}" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Ngày hoàn thành</x-ui.label>
                                <x-ui.input name="completed_at" id="completed_at" type="date" class="sc-input"
                                       value="{{ old('completed_at') }}" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Bảo hành đến</x-ui.label>
                                <x-ui.input name="warranty_to" id="warranty_to" type="date" class="sc-input"
                                       value="{{ old('warranty_to') }}" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Mốc bảo hành 1</x-ui.label>
                                <x-ui.input name="warranty_reminder_1_at" id="warranty_reminder_1_at" type="date" class="sc-input"
                                       value="{{ old('warranty_reminder_1_at') }}" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Mốc bảo hành 2</x-ui.label>
                                <x-ui.input name="warranty_reminder_2_at" id="warranty_reminder_2_at" type="date" class="sc-input"
                                       value="{{ old('warranty_reminder_2_at') }}" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Mốc bảo hành 3</x-ui.label>
                                <x-ui.input name="warranty_reminder_3_at" id="warranty_reminder_3_at" type="date" class="sc-input"
                                       value="{{ old('warranty_reminder_3_at') }}" />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Phụ trách kỹ thuật</x-ui.label>
                                <x-ui.input name="technician_name" class="sc-input"
                                       value="{{ old('technician_name') }}"
                                       placeholder="VD: Anh Thư, Anh B..." />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Link Monitoring</x-ui.label>
                                <x-ui.input name="monitoring_link" class="sc-input"
                                       value="{{ old('monitoring_link') }}"
                                       placeholder="VD: https://..." />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Tài khoản Monitoring</x-ui.label>
                                <x-ui.input name="monitoring_account" class="sc-input"
                                       value="{{ old('monitoring_account') }}"
                                       placeholder="VD: user@email.com" />
                            </div>

                            <input type="hidden" name="installed_at" id="installed_at" value="{{ old('installed_at') }}">
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            </div>

            {{-- RIGHT --}}
            <div class="col-lg-4">
                <x-ui.card class="border-0 shadow-ego ego-card sticky-lg-top" style="top:90px;">
                    <x-ui.card-header class="bg-white border-0 tw:py-4">
                        <div class="tw:font-bold"><i class="bi bi-check2-circle"></i> Hoàn tất</div>
                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Kiểm tra thông tin trước khi lưu.</div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="summary-box tw:mb-4">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Hệ</div>
                            <div class="summary-money"><span id="summarySystemKwp">0</span> kWp</div>
                        </div>

                        <div class="summary-box tw:mb-4">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Inverter</div>
                            <div class="tw:font-bold"><span id="summaryInverterBrand">—</span></div>
                            <div class="summary-sub"><span id="summaryInverterKw">0</span> kW</div>
                        </div>

                        <div class="summary-box tw:mb-4">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Pin lưu trữ</div>
                            <div class="tw:font-bold"><span id="summaryBatteryBrand">—</span></div>
                            <div class="summary-sub"><span id="summaryBatteryKwh">0</span> kWh</div>
                        </div>

                        <div class="summary-box tw:mb-4">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Tấm pin</div>
                            <div class="tw:font-bold"><span id="summaryPanelBrand">—</span></div>
                            <div class="summary-sub">
                                <span id="summaryPanelWp">0</span> W × <span id="summaryPanelQty">0</span> tấm
                            </div>
                        </div>

                        <hr>

                        <div class="summary-box tw:mb-4">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng hợp đồng</div>
                            <div class="summary-money" id="summaryContract">0 đ</div>
                        </div>

                        <div class="summary-box tw:mb-4">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng các đợt thanh toán</div>
                            <div class="summary-money" id="summaryTerms">0 đ</div>
                        </div>

                        <div class="summary-box tw:mb-4">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chênh lệch</div>
                            <div class="summary-money" id="summaryDiff">0 đ</div>
                        </div>

                        <div class="progress payment-progress tw:mb-4">
                            <div class="progress-bar" id="paymentProgressBar" style="width:0%"></div>
                        </div>

                        <x-ui.button variant="none" size="none" class="btn-ego tw:w-full tw:py-[6px] tw:px-3" type="submit">
                            <i class="bi bi-save"></i> Lưu công trình
                        </x-ui.button>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
        </div>
    </form>
</div>

<style>
:root{
    --ego:#0BC9AA;
    --ego-dark:#08a88f;
    --ego-soft:#e8fbf7;
    --ink:#0f172a;
    --muted:#64748b;
    --line:#e6f4f1;
}
.ego-sites-form{
    background:radial-gradient(circle at top left, rgba(11,201,170,.12), transparent 28%), linear-gradient(180deg,#f7fffd 0%,#fff 55%);
    min-height:calc(100vh - 64px);
}
.page-icon,.icon-pill{
    display:inline-flex;width:38px;height:38px;border-radius:14px;align-items:center;justify-content:center;
    background:rgba(11,201,170,.12);color:#0f766e;border:1px solid rgba(11,201,170,.20);
}
.shadow-ego{box-shadow:0 14px 34px rgba(2,44,34,.07);}
.ego-card{border:1px solid var(--line)!important;border-radius:18px!important;overflow:hidden;background:rgba(255,255,255,.96);}
.ego-card .card-header,
    .ego-card [data-ego-card-header]{border-radius:18px 18px 0 0;}
/* .form-control/.form-select giữ lại CHO HÀNG DO JS DỰNG lúc chạy (thiết bị,
   vật tư) — phần đó không chuyển sang component được. Bỏ đi là hai hàng ấy mất
   bo góc 13px và màu viền. */
.sc-input,.form-control,.form-select{border-radius:13px;border-color:rgba(15,23,42,.12);}
.sc-input:focus,.form-control:focus,.form-select:focus{border-color:rgba(11,201,170,.7);box-shadow:0 0 0 .2rem rgba(11,201,170,.12);}
.btn-ego{
    background:linear-gradient(135deg,var(--ego),var(--ego-dark))!important;border:0!important;color:#fff!important;
    border-radius:14px!important;font-weight:800;box-shadow:0 12px 24px rgba(11,201,170,.24);
}
.btn-outline-ego{
    border-color:rgba(11,201,170,.42)!important;color:#0f766e!important;border-radius:13px!important;
    font-weight:800;background:#fff;
}
.btn-outline-ego:hover{background:var(--ego-soft)!important;}
.input-money{display:flex;align-items:center;border:1px solid rgba(15,23,42,.12);border-radius:13px;overflow:hidden;background:#fff;}
.input-money input{border:0!important;box-shadow:none!important;}
.input-money span{padding:0 12px;color:var(--muted);font-weight:800;}
.ego-table thead th{
    background:var(--ego-soft)!important;border-bottom:1px solid var(--line)!important;color:#0b3b36!important;
    font-weight:900!important;white-space:nowrap;
}
.ego-table td{border-color:var(--line)!important;vertical-align:middle;}
.finance-mini-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;}
.mini-box,.summary-box,.system-box{
    border:1px solid rgba(15,23,42,.07);background:#fff;border-radius:16px;padding:13px;
}
.system-box{background:linear-gradient(135deg, rgba(11,201,170,.055), #fff);}
.summary-money{font-size:22px;font-weight:950;color:var(--ink);letter-spacing:-.4px;}
.summary-sub{font-size:15px;color:var(--muted);font-weight:800;}
.payment-progress{height:12px;background:rgba(15,23,42,.08);border-radius:999px;overflow:hidden;}
.payment-progress .progress-bar{background:linear-gradient(90deg,var(--ego),#10b981);}
.payment-progress .progress-bar.warn{background:linear-gradient(90deg,#f59e0b,#ef4444);}
@media(max-width:991.98px){
    .finance-mini-summary{grid-template-columns:repeat(2,minmax(0,1fr));}
    .sticky-lg-top{position:static!important;}
}
@media(max-width:575.98px){
    .finance-mini-summary{grid-template-columns:1fr;}
}
</style>

<script>
(function(){
    const money = n => {
        n = Number(n || 0);
        return n.toLocaleString('vi-VN') + ' đ';
    };

    const qs = (s, root=document) => root.querySelector(s);
    const qsa = (s, root=document) => Array.from(root.querySelectorAll(s));

    const contractInput = qs('#contract_amount');
    const paymentTermsTbody = qs('#paymentTermsTbody');

    function setText(id, text){
        const el = qs('#' + id);
        if (el) el.textContent = text;
    }

    function renumberTerms(){
        qsa('.payment-term-row', paymentTermsTbody).forEach((row, i) => {
            const idx = row.querySelector('.term-idx');
            if (idx) idx.textContent = i + 1;

            qsa('input', row).forEach(input => {
                input.name = input.name.replace(/payment_terms\[\d+\]/, 'payment_terms[' + i + ']');
            });

            const removeBtn = row.querySelector('.btnRemoveTerm');
            if (removeBtn) removeBtn.disabled = qsa('.payment-term-row', paymentTermsTbody).length <= 1;
        });
    }

    function calcFinance(){
        const contract = Number(contractInput?.value || 0);
        let totalTerms = 0;

        qsa('.payment-term-row', paymentTermsTbody).forEach(row => {
            const percentInput = row.querySelector('.term-percent');
            const amountInput = row.querySelector('.term-amount');

            const percent = Number(percentInput?.value || 0);
            let amount = Number(amountInput?.value || 0);

            if (percent > 0 && contract > 0) {
                amount = Math.round(contract * percent / 100);
                if (amountInput && document.activeElement !== amountInput) {
                    amountInput.value = amount;
                }
            }

            totalTerms += amount;
        });

        const diff = contract - totalTerms;
        const progress = contract > 0 ? Math.min(100, Math.max(0, totalTerms / contract * 100)) : 0;

        setText('financeContractMini', money(contract));
        setText('financeTermsMini', money(totalTerms));
        setText('financeDiffMini', money(diff));
        setText('financeTermCountMini', String(qsa('.payment-term-row', paymentTermsTbody).length));

        setText('summaryContract', money(contract));
        setText('summaryTerms', money(totalTerms));
        setText('summaryDiff', money(diff));

        const bar = qs('#paymentProgressBar');
        if (bar) {
            bar.style.width = progress + '%';
            bar.classList.toggle('warn', diff !== 0 && contract > 0);
        }

        ['financeDiffMini','summaryDiff'].forEach(id => {
            const el = qs('#' + id);
            if (!el) return;
            el.classList.remove('text-success','text-danger');
            if (contract > 0 || totalTerms > 0) {
                el.classList.add(diff === 0 ? 'text-success' : 'text-danger');
            }
        });
    }

    function createTermRow(){
        const tpl = qs('.payment-term-row', paymentTermsTbody);
        const clone = tpl.cloneNode(true);
        qsa('input', clone).forEach(input => input.value = '');
        const removeBtn = clone.querySelector('.btnRemoveTerm');
        if (removeBtn) removeBtn.disabled = false;
        return clone;
    }

    qs('#btnAddPaymentTerm')?.addEventListener('click', () => {
        paymentTermsTbody.appendChild(createTermRow());
        renumberTerms();
        calcFinance();
    });

    paymentTermsTbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btnRemoveTerm');
        if (!btn) return;

        const rows = qsa('.payment-term-row', paymentTermsTbody);
        if (rows.length <= 1) return;

        btn.closest('.payment-term-row')?.remove();
        renumberTerms();
        calcFinance();
    });

    paymentTermsTbody?.addEventListener('input', calcFinance);
    contractInput?.addEventListener('input', calcFinance);

    const panelBrand = qs('[name="panel_brand"]');
    const panelWp = qs('#solar_panel_wp');
    const panelQty = qs('#solar_panel_qty');
    const systemKwp = qs('#system_kwp');

    const inverterBrand = qs('[name="inverter_brand"]');
    const inverterKw = qs('[name="system_kw_ac"]');

    const batteryBrand = qs('[name="battery_brand"]');
    const batteryKwh = qs('[name="battery_kwh"]');

    function calcSystem(){
        const wp = Number(panelWp?.value || 0);
        const qty = Number(panelQty?.value || 0);
        const kwp = wp > 0 && qty > 0 ? (wp * qty / 1000) : Number(systemKwp?.value || 0);

        if (systemKwp && wp > 0 && qty > 0) {
            systemKwp.value = kwp.toFixed(3).replace(/\.?0+$/, '');
        }

        setText('summarySystemKwp', kwp ? kwp.toFixed(3).replace(/\.?0+$/, '') : '0');
        setText('summaryPanelBrand', panelBrand?.value || '—');
        setText('summaryPanelWp', panelWp?.value || '0');
        setText('summaryPanelQty', panelQty?.value || '0');

        setText('summaryInverterBrand', inverterBrand?.value || '—');
        setText('summaryInverterKw', inverterKw?.value || '0');

        setText('summaryBatteryBrand', batteryBrand?.value || '—');
        setText('summaryBatteryKwh', batteryKwh?.value || '0');
    }

    [panelBrand, panelWp, panelQty, systemKwp, inverterBrand, inverterKw, batteryBrand, batteryKwh].forEach(el => {
        el?.addEventListener('input', calcSystem);
        el?.addEventListener('change', calcSystem);
    });

    const completedAt = qs('#completed_at');
    const warrantyTo = qs('#warranty_to');
    const w1 = qs('#warranty_reminder_1_at');
    const w2 = qs('#warranty_reminder_2_at');
    const w3 = qs('#warranty_reminder_3_at');
    const installedAt = qs('#installed_at');

    function addMonths(dateString, months){
        if (!dateString) return '';
        const d = new Date(dateString + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return '';
        d.setMonth(d.getMonth() + months);
        return d.toISOString().slice(0, 10);
    }

    completedAt?.addEventListener('change', () => {
        const base = completedAt.value;
        if (!base) return;

        if (installedAt && !installedAt.value) installedAt.value = base;
        if (w1 && !w1.value) w1.value = addMonths(base, 6);
        if (w2 && !w2.value) w2.value = addMonths(base, 12);
        if (w3 && !w3.value) w3.value = addMonths(base, 24);
        if (warrantyTo && !warrantyTo.value) warrantyTo.value = addMonths(base, 60);
    });

    renumberTerms();
    calcFinance();
    calcSystem();
})();
</script>
@endsection