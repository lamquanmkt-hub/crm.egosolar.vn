{{-- $egoSiteCompanyOptions do EgoDefaultCompany cấp qua view composer. Trước đây
     cùng một khối truy vấn companies được chép vào cả ba view sites/index,
     sites/create, sites/edit — sửa điều kiện lọc ở một chỗ mà quên hai chỗ kia
     là ra ba danh sách khác nhau trên ba trang. --}}
@extends('layouts.app')

@section('content')

<div class="container-fluid tw:px-6 tw:py-4 ego-sites-form">

    {{-- HEADER --}}
    <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-2 tw:mb-4 ego-header">
        <div>
            <div class="tw:flex tw:items-center tw:gap-2 tw:mb-1">
                <span class="page-icon">
                    <i class="bi bi-pencil-square"></i>
                </span>
                <h4 class="tw:font-bold tw:mb-0">Sửa {{ $projectLabel }}</h4>
            </div>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">
                Cập nhật thông tin công trình, cấu hình hệ thống, doanh thu dự án và các đợt thanh toán.
            </div>
        </div>

        <x-ui.button variant="outline-secondary" :href="$projectIndexUrl">
            <i class="bi bi-arrow-left"></i> Quay lại
        </x-ui.button>
    </div>

    {{-- ERROR --}}
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

    <form method="POST" action="{{ url('/cong-trinh/'.$site->id.'/cap-nhat') }}" id="siteEditForm">
        @csrf

        <div class="row g-3">

            {{-- LEFT --}}
            <div class="col-lg-8">

                {{-- THÔNG TIN CÔNG TRÌNH --}}
                <x-ui.card class="border-0 shadow-ego ego-card" style="border-radius:18px;">
                    <x-ui.card-header class="bg-white border-0 tw:py-4" style="border-radius:18px 18px 0 0;">
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


                            {{-- EGO_SITE_COMPANY_EDIT_FIELD_START --}}
                            <div class="col-md-4">
                                <x-ui.label>Công ty <span class="text-danger">*</span></x-ui.label>
                                <x-ui.select class="site-input" name="company_id" required>
                                    <option value="">-- Chọn công ty --</option>
                                    @foreach($egoSiteCompanyOptions as $company)
                                        <option value="{{ $company->id }}" {{ (string)$egoOldSiteCompanyId === (string)$company->id ? 'selected' : '' }}>
                                            {{ trim(($company->code ?? '') . ' - ' . ($company->name ?? '')) }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                            {{-- EGO_SITE_COMPANY_EDIT_FIELD_END --}}


                            {{-- EGO_PROJECT_TYPE_EDIT_FIELD_START --}}
                            <div class="col-md-4">
                                <x-ui.label>Loại dự án <span class="text-danger">*</span></x-ui.label>
                                <x-ui.select class="site-input" name="project_type" required>
                                    <option value="residential" {{ old('project_type', $projectType) === 'residential' ? 'selected' : '' }}>
                                        Dân dụng
                                    </option>
                                    <option value="factory" {{ old('project_type', $projectType) === 'factory' ? 'selected' : '' }}>
                                        Nhà xưởng
                                    </option>
                                </x-ui.select>
                            </div>
                            {{-- EGO_PROJECT_TYPE_EDIT_FIELD_END --}}

                            <div class="col-md-5">
                                <x-ui.label>Tên công trình <span class="text-danger">*</span></x-ui.label>
                                <x-ui.input class="site-input" name="name" required value="{{ old('name', $site->name) }}" placeholder="VD: Công trình Quận 7 - Anh A" />
                            </div>

                            <div class="col-md-3">
                                <x-ui.label>Trạng thái</x-ui.label>
                                <x-ui.select class="site-input" name="status">
                                    <option value="" {{ $st==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="planning"   {{ $st=='planning'?'selected':'' }}>Chuẩn bị</option>
                                    <option value="installing" {{ $st=='installing'?'selected':'' }}>Đang lắp đặt</option>
                                    <option value="done"       {{ $st=='done'?'selected':'' }}>Đã hoàn thành</option>
                                    <option value="warranty"   {{ $st=='warranty'?'selected':'' }}>Đang bảo hành</option>
                                </x-ui.select>
                            </div>

                            <div class="col-md-12">
                                <x-ui.label>Địa chỉ</x-ui.label>
                                <x-ui.input class="site-input" name="address" value="{{ old('address', $site->address) }}" placeholder="VD: 123 Lê Văn Lương, Quận 7, TP.HCM" />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Người liên hệ</x-ui.label>
                                <x-ui.input class="site-input" name="contact_name" value="{{ old('contact_name', $site->contact_name) }}" placeholder="VD: Anh A" />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>SĐT liên hệ</x-ui.label>
                                <x-ui.input class="site-input" name="contact_phone" value="{{ old('contact_phone', $site->contact_phone) }}" placeholder="VD: 0909xxxxxx" />
                            </div>

                            <div class="col-md-12">
                                <x-ui.label>Ghi chú</x-ui.label>
                                <x-ui.input as="textarea" class="site-input" name="note" rows="3" placeholder="Ghi chú nội bộ...">{{ old('note', $site->note) }}</x-ui.input>
                            </div>

                        </div>
                    </x-ui.card-body>
                </x-ui.card>

                {{-- TÀI CHÍNH CÔNG TRÌNH --}}
                <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4 finance-card" style="border-radius:18px;">
                    <x-ui.card-header class="bg-white border-0 tw:py-4" style="border-radius:18px 18px 0 0;">
                        <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-2">
                            <div class="tw:flex tw:items-center tw:gap-2">
                                <span class="icon-pill finance-icon"><i class="bi bi-cash-coin"></i></span>
                                <div>
                                    <div class="tw:font-bold">Tài chính công trình</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                        Cập nhật tổng doanh thu dự án và các đợt thanh toán.
                                    </div>
                                </div>
                            </div>

                            <span class="finance-badge">
                                <i class="bi bi-graph-up-arrow"></i> Project Finance
                            </span>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="finance-hero tw:mb-4">
                            <div class="row g-3 tw:items-end">

                                <div class="col-md-5">
                                    <x-ui.label>Giá trị hợp đồng / Tổng doanh thu dự án</x-ui.label>
                                    <div class="input-money">
                                        <x-ui.input class="site-input tw:text-right tw:font-bold" name="contract_amount" id="contract_amount" type="number" step="any" min="0" value="{{ $contractAmount }}" placeholder="VD: 150000000" />
                                        <span>đ</span>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <x-ui.label>Ngày ký hợp đồng</x-ui.label>
                                    <x-ui.input class="site-input" name="contract_signed_at" type="date" value="{{ $contractSignedAt }}" />
                                </div>

                                <div class="col-md-4">
                                    <x-ui.label>Ghi chú tài chính</x-ui.label>
                                    <x-ui.input class="site-input" name="finance_note" value="{{ $financeNote }}" placeholder="VD: cọc 30%, giao vật tư 40%, nghiệm thu 30%" />
                                </div>

                            </div>
                        </div>


                        <div class="tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2 tw:mb-2">
                            <div>
                                <div class="tw:font-bold">Các đợt thanh toán</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                    Nhập % hoặc số tiền. Nếu có %, hệ thống tự tính theo giá trị hợp đồng.
                                </div>
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
                                @foreach($paymentTermRows as $i => $term)
                                    <tr class="payment-term-row">
                                        <input type="hidden" name="payment_terms[{{ $i }}][id]" value="{{ $term['id'] ?? '' }}">
                                        <td class="tw:text-center term-idx">{{ $i + 1 }}</td>

                                        <td>
                                            <x-ui.input class="site-input" name="payment_terms[{{ $i }}][name]" value="{{ $term['name'] ?? '' }}" placeholder="VD: Đợt {{ $i + 1 }} - Đặt cọc" />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input tw:text-right term-percent" name="payment_terms[{{ $i }}][percent]" type="number" min="0" max="100" step="any" value="{{ $term['percent'] ?? '' }}" placeholder="%" />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input tw:text-right term-amount" name="payment_terms[{{ $i }}][amount]" type="number" min="0" step="any" value="{{ $term['amount'] ?? '' }}" placeholder="Số tiền" />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input" name="payment_terms[{{ $i }}][due_date]" type="date" value="{{ $term['due_date'] ?? '' }}" />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input" name="payment_terms[{{ $i }}][note]" value="{{ $term['note'] ?? '' }}" placeholder="Ghi chú đợt thanh toán" />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.button variant="outline-danger" size="sm" class="btnRemoveTerm" type="button" :disabled="count($paymentTermRows) === 1">
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
                                <div class="tw:font-bold" id="financeTermCountMini">{{ count($paymentTermRows) }}</div>
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
                                        <x-ui.input class="site-input tw:text-right tw:font-bold" name="labor_cost" type="number" step="any" min="0" value="{{ old('labor_cost', $site->labor_cost ?? 0) }}" placeholder="VD: 5000000" />
                                        <span>đ</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="tw:p-4 rounded-4 border bg-white tw:h-full">
                                    <x-ui.label class="tw:font-semibold">Chi phí vận chuyển</x-ui.label>
                                    <div class="input-money">
                                        <x-ui.input class="site-input tw:text-right tw:font-bold" name="transport_cost" type="number" step="any" min="0" value="{{ old('transport_cost', $site->transport_cost ?? 0) }}" placeholder="VD: 1000000" />
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

                                    <input type="hidden" name="other_cost" value="{{ old('other_cost', $site->other_cost ?? 0) }}" data-other-cost-hidden>
                                    <input type="hidden" name="other_cost_note" value="{{ old('other_cost_note', $site->other_cost_note ?? '') }}" data-other-cost-note-hidden>

                                    <div data-other-cost-rows>
                                        <div class="row g-2 tw:items-end other-cost-row tw:mb-2" data-other-cost-row>
                                            <div class="col-md-4">
                                                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Số tiền</x-ui.label>
                                                <div class="input-money">
                                                    <x-ui.input class="site-input tw:text-right" type="number" step="any" min="0" value="{{ old('other_cost', $site->other_cost ?? 0) }}" placeholder="VD: 2000000" data-other-cost-amount />
                                                    <span>đ</span>
                                                </div>
                                            </div>

                                            <div class="col-md-7">
                                                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Nội dung</x-ui.label>
                                                <x-ui.input class="site-input" type="text" value="{{ old('other_cost_note', $site->other_cost_note ?? '') }}" placeholder="VD: Phụ kiện, ăn ở, bốc xếp..." data-other-cost-note />
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


                {{-- THÔNG TIN HỆ THỐNG --}}
                <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4" style="border-radius:18px;">
                    <x-ui.card-header class="bg-white border-0 tw:py-4" style="border-radius:18px 18px 0 0;">
                        <div class="tw:flex tw:items-center tw:gap-2">
                            <span class="icon-pill"><i class="bi bi-lightning-charge"></i></span>
                            <div>
                                <div class="tw:font-bold">Thông tin hệ thống</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Thông tin tóm tắt hiển thị ở danh sách công trình.</div>
                            </div>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="row g-3">

                            <div class="col-md-4">
                                <x-ui.label>Công suất DC (kWp)</x-ui.label>
                                <x-ui.input class="site-input" name="system_kwp" type="number" step="any" min="0" value="{{ old('system_kwp', $site->system_kwp ?? '') }}" placeholder="VD: 6.60" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Công suất AC (kW)</x-ui.label>
                                <x-ui.input class="site-input" name="system_kw_ac" type="number" step="any" min="0" value="{{ old('system_kw_ac', $site->system_kw_ac ?? '') }}" placeholder="VD: 5.00" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Loại hệ</x-ui.label>
                                <x-ui.select class="site-input" name="system_type">
                                    <option value="" {{ $stype==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="on_grid"  {{ $stype=='on_grid'?'selected':'' }}>On-grid</option>
                                    <option value="hybrid"   {{ $stype=='hybrid'?'selected':'' }}>Hybrid</option>
                                    <option value="off_grid" {{ $stype=='off_grid'?'selected':'' }}>Off-grid</option>
                                    <option value="other" {{ $stype=='other'?'selected':'' }}>Khác</option>
                                </x-ui.select>
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Điện áp</x-ui.label>
                                <x-ui.select class="site-input" name="phase">
                                    <option value="" {{ $ph==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="1_phase" {{ $ph=='1_phase'?'selected':'' }}>1 pha</option>
                                    <option value="3_phase" {{ $ph=='3_phase'?'selected':'' }}>3 pha</option>
                                </x-ui.select>
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Ngày lắp đặt</x-ui.label>
                                <x-ui.input class="site-input" name="installed_at" type="date" value="{{ $installedAt }}" />
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Bảo hành đến</x-ui.label>
                                <x-ui.input class="site-input" name="warranty_to" type="date" value="{{ $warrantyTo }}" />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Phụ trách kỹ thuật</x-ui.label>

                                <input type="hidden" name="technician_name" id="technician_name_hidden"
                                       value="{{ $techRaw }}">

                                <div class="tech-box">
                                    <div class="tech-chips" id="techChips">
                                        @foreach($techList as $t)
                                            <span class="tech-chip" data-val="{{ $t }}">
                                                {{ $t }}
                                                <button type="button" class="tech-x" aria-label="remove">×</button>
                                            </span>
                                        @endforeach
                                    </div>

                                    <x-ui.input class="site-input tech-input" type="text" id="techInput" placeholder="Nhập tên KTV rồi Enter (vd: Anh Thu, Anh B...)" />

                                    <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">Mẹo: bấm Enter để thêm, bấm × để xoá.</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Link Monitoring</x-ui.label>
                                <x-ui.input class="site-input" name="monitoring_link" value="{{ old('monitoring_link', $site->monitoring_link ?? '') }}" placeholder="VD: https://..." />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Tài khoản Monitoring</x-ui.label>
                                <x-ui.input class="site-input" name="monitoring_account" value="{{ old('monitoring_account', $site->monitoring_account ?? '') }}" placeholder="VD: user@email.com / sđt / username" />
                            </div>

                            <div class="col-md-6">
                                <x-ui.label>Giai đoạn</x-ui.label>
                                <x-ui.select class="site-input" name="stage">
                                    <option value="" {{ $stage==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="survey"       {{ $stage=='survey'?'selected':'' }}>Khảo sát</option>
                                    <option value="design"       {{ $stage=='design'?'selected':'' }}>Thiết kế</option>
                                    <option value="installation" {{ $stage=='installation'?'selected':'' }}>Thi công</option>
                                    <option value="acceptance"   {{ $stage=='acceptance'?'selected':'' }}>Nghiệm thu</option>
                                    <option value="operation"    {{ $stage=='operation'?'selected':'' }}>Vận hành</option>
                                </x-ui.select>
                            </div>

                        </div>
                    </x-ui.card-body>
                </x-ui.card>

                {{-- THIẾT BỊ & CẤU HÌNH --}}
                <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4" style="border-radius:18px;">
                    <x-ui.card-header class="bg-white border-0 tw:py-4 tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2" style="border-radius:18px 18px 0 0;">
                        <div>
                            <div class="tw:flex tw:items-center tw:gap-2">
                                <span class="icon-pill"><i class="bi bi-cpu"></i></span>
                                <div class="tw:font-bold">Thiết bị & Cấu hình</div>
                            </div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">Thêm inverter / pin lưu trữ / tấm pin cho công trình lớn.</div>
                        </div>

                        <x-ui.button variant="none" size="none" class="btn-outline-ego tw:py-[6px] tw:px-3" type="button" id="btnAddDevice">
                            <i class="bi bi-plus-lg"></i> Thêm thiết bị
                        </x-ui.button>
                    </x-ui.card-header>

                    <x-ui.card-body class="tw:p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle tw:mb-0 ego-table">
                                <thead class="table-light">
                                <tr>
                                    <th style="width:54px" class="tw:text-center">#</th>
                                    <th class="ego-col-type">Loại</th>
                                    <th class="ego-col-brand">Hãng</th>
                                    <th class="ego-col-model">Model</th>
                                    <th class="ego-col-serial">Serial</th>
                                    <th class="ego-col-kw tw:text-right">kW / kWp</th>
                                    <th class="ego-col-kwh tw:text-right">kWh</th>
                                    <th class="ego-col-qty tw:text-right">SL</th>
                                    <th class="ego-col-date">BH đến</th>
                                    <th class="ego-col-del tw:text-right">Xóa</th>
                                </tr>
                                </thead>

                                <tbody id="devicesTbody">
                                @foreach($devicesRows as $i => $d)

                                    <tr class="device-row">
                                        <td class="tw:text-center device-idx">{{ $i + 1 }}</td>

                                        <td>
                                            <x-ui.select class="site-input device-type" name="devices[{{ $i }}][type]">
                                                <option value="inverter"        {{ $d['type']=='inverter'?'selected':'' }}>Inverter</option>
                                                <option value="hybrid_inverter" {{ $d['type']=='hybrid_inverter'?'selected':'' }}>Hybrid Inverter</option>
                                                <option value="battery"         {{ $d['type']=='battery'?'selected':'' }}>Pin lưu trữ</option>
                                                <option value="solar_panel"     {{ in_array($d['type'], ['solar_panel', 'pv'], true) ? 'selected' : '' }}>Tấm pin NLMT</option>
                                                <option value="meter"           {{ $d['type']=='meter'?'selected':'' }}>Smart Meter</option>
                                                <option value="ac_panel"        {{ $d['type']=='ac_panel'?'selected':'' }}>Tủ AC</option>
                                                <option value="dc_panel"        {{ $d['type']=='dc_panel'?'selected':'' }}>Tủ DC</option>
                                                <option value="other"           {{ $d['type']=='other'?'selected':'' }}>Khác</option>
                                            </x-ui.select>
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input" name="devices[{{ $i }}][brand]" value="{{ $d['brand'] ?? '' }}" placeholder="VD: Goodwe / Jinko" />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input" name="devices[{{ $i }}][model]" value="{{ $d['model'] ?? '' }}" placeholder="VD: GW6K-ET / 550W..." />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input" name="devices[{{ $i }}][serial]" value="{{ $d['serial'] ?? '' }}" placeholder="SN-..." />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.input class="site-input tw:text-right device-kw" name="devices[{{ $i }}][power_kw]" type="number" step="any" min="0" value="{{ $d['power_kw'] ?? '' }}" placeholder="kW/kWp" />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.input class="site-input tw:text-right device-kwh" name="devices[{{ $i }}][capacity_kwh]" type="number" step="any" min="0" value="{{ $d['capacity_kwh'] ?? '' }}" placeholder="kWh" />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.input class="site-input tw:text-right device-qty" name="devices[{{ $i }}][qty]" type="number" min="1" value="{{ $d['qty'] ?? 1 }}" />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input" name="devices[{{ $i }}][warranty_to]" type="date" value="{{ $d['warranty_to'] ?? '' }}" />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.button variant="outline-danger" size="sm" class="btnRemoveDevice" type="button" :disabled="count($devicesRows) == 1">
                                                <i class="bi bi-trash"></i>
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="tw:p-4 border-top tw:flex flex-wrap tw:justify-between tw:gap-2">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                Gợi ý: INV/Hybrid nhập <b>kW</b>, BAT nhập <b>kWh</b>, PV nhập <b>kWp</b>.
                            </div>
                            <div class="small">
                                <span class="badge bg-light tw:text-[#212529]! border">INV</span>
                                <span class="badge bg-light tw:text-[#212529]! border">BAT</span>
                                <span class="badge bg-light tw:text-[#212529]! border">PV</span>
                                <span class="badge bg-light tw:text-[#212529]! border">METER</span>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>

                {{-- VẬT TƯ DỰ KIẾN --}}
                <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4" style="border-radius:18px;">
                    <x-ui.card-header class="bg-white border-0 tw:py-4 tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2" style="border-radius:18px 18px 0 0;">
                        <div>
                            <div class="tw:flex tw:items-center tw:gap-2">
                                <span class="icon-pill"><i class="bi bi-box-seam"></i></span>
                                <div class="tw:font-bold">Vật tư dự kiến</div>
                            </div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">
                                Dữ liệu này sẽ dùng để đối chiếu với vật tư thực tế.
                            </div>
                        </div>

                        <x-ui.button variant="none" size="none" class="btn-outline-ego tw:py-[6px] tw:px-3" type="button" id="btnAddPlan">
                            <i class="bi bi-plus-lg"></i> Thêm vật tư
                        </x-ui.button>
                    </x-ui.card-header>

                    <x-ui.card-body class="tw:p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle tw:mb-0 ego-table">
                                <thead class="table-light">
                                <tr>
                                    <th style="width:50px" class="tw:text-center">#</th>
                                    <th style="min-width:260px">Tên vật tư</th>
                                    <th style="width:150px">Đơn vị</th>
                                    <th style="width:150px" class="tw:text-right">Số lượng</th>
                                    <th style="width:80px" class="tw:text-right">Xóa</th>
                                </tr>
                                </thead>

                                <tbody id="plansTbody">
                                @foreach($plannedRows as $i => $p)
                                    <tr class="plan-row">
                                        <td class="tw:text-center plan-idx">{{ $i + 1 }}</td>

                                        <td>
                                            <x-ui.input class="site-input" name="planned[{{ $i }}][name]" placeholder="VD: Dây cáp DC 6,0mm" value="{{ $p['name'] ?? '' }}" />
                                        </td>

                                        <td>
                                            <x-ui.input class="site-input" name="planned[{{ $i }}][unit]" placeholder="m/cái/bộ..." value="{{ $p['unit'] ?? '' }}" />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.input class="site-input tw:text-right" name="planned[{{ $i }}][qty]" type="number" min="0" step="any" value="{{ $p['qty'] ?? 0 }}" />
                                        </td>

                                        <td class="tw:text-right">
                                            <x-ui.button variant="outline-danger" size="sm" class="btnRemovePlan" type="button" :disabled="count($plannedRows) == 1">
                                                <i class="bi bi-trash"></i>
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="tw:p-4 border-top tw:flex flex-wrap tw:justify-between tw:gap-2">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                Gợi ý: Nhập đúng đơn vị để tránh nhầm m/cái/bộ/cuộn...
                            </div>
                            <div class="small">
                                <span class="badge bg-light tw:text-[#212529]! border">Plan</span>
                                <span class="badge bg-light tw:text-[#212529]! border">Đối chiếu</span>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>

            </div>

            {{-- RIGHT --}}
            <div class="col-lg-4">
                <div class="sticky-top" style="top:90px;">

                    <x-ui.card class="border-0 shadow-ego ego-card tw:mb-4" style="border-radius:18px;">
                        <x-ui.card-header class="bg-white border-0 tw:py-4" style="border-radius:18px 18px 0 0;">
                            <div class="tw:flex tw:items-center tw:gap-2">
                                <span class="icon-pill finance-icon"><i class="bi bi-pie-chart"></i></span>
                                <div>
                                    <div class="tw:font-bold">Tóm tắt tài chính</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Kiểm tra doanh thu và đợt thanh toán.</div>
                                </div>
                            </div>
                        </x-ui.card-header>

                        <x-ui.card-body>
                            <div class="finance-summary-main">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng doanh thu dự án</div>
                                <div class="amount-big" id="summaryContract">0 đ</div>
                            </div>

                            <div class="summary-grid tw:mt-4">
                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng các đợt</div>
                                    <div class="tw:font-bold" id="summaryTerms">0 đ</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Chênh lệch</div>
                                    <div class="tw:font-bold" id="summaryDiff">0 đ</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Số đợt</div>
                                    <div class="tw:font-bold" id="summaryTermCount">{{ count($paymentTermRows) }}</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Tỷ lệ chia</div>
                                    <div class="tw:font-bold"><span id="summaryPercent">0</span>%</div>
                                </div>
                            </div>

                            <div class="progress finance-progress tw:mt-4">
                                <div class="progress-bar" id="paymentProgressBar" style="width:0%"></div>
                            </div>

                            <div class="summary-hint tw:mt-4">
                                <div class="tw:flex tw:items-start tw:gap-2">
                                    <div class="hint-ic"><i class="bi bi-stars"></i></div>
                                    <div>
                                        <div class="tw:font-semibold">Luồng tài chính</div>
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                            Sửa doanh thu → cập nhật đợt thanh toán → phiếu thu link vào công trình và từng đợt.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-ui.card-body>

                        <x-ui.card-footer class="bg-white border-0 tw:p-4" style="border-radius:0 0 18px 18px;">
                            <x-ui.button variant="none" size="none" type="submit" class="btn-ego tw:w-full">
                                <i class="bi bi-save"></i> Lưu thay đổi
                            </x-ui.button>
                            <x-ui.button variant="outline-secondary" class="tw:w-full tw:mt-2" :href="url('/cong-trinh')">
                                Quay lại
                            </x-ui.button>
                        </x-ui.card-footer>
                    </x-ui.card>

                    <x-ui.card class="border-0 shadow-ego ego-card" style="border-radius:18px;">
                        <x-ui.card-header class="bg-white border-0 tw:py-4" style="border-radius:18px 18px 0 0;">
                            <div class="tw:flex tw:items-center tw:gap-2">
                                <span class="icon-pill"><i class="bi bi-card-checklist"></i></span>
                                <div>
                                    <div class="tw:font-bold">Tóm tắt kỹ thuật</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Tự tính theo thiết bị & vật tư.</div>
                                </div>
                            </div>
                        </x-ui.card-header>

                        <x-ui.card-body>
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Số thiết bị</div>
                                    <div class="tw:font-bold" id="deviceCount">{{ count($devicesRows) }}</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Dòng vật tư</div>
                                    <div class="tw:font-bold" id="planCount">{{ count($plannedRows) }}</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng Inverter</div>
                                    <div class="tw:font-bold"><span id="sumInvKw">—</span> <span class="unit">kW</span></div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng Pin lưu trữ</div>
                                    <div class="tw:font-bold"><span id="sumBatKwh">—</span> <span class="unit">kWh</span></div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng Pin NLMT</div>
                                    <div class="tw:font-bold"><span id="sumPvKwp">—</span> <span class="unit">kWp</span></div>
                                </div>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>

                </div>
            </div>

        </div>
    </form>
</div>

<script>
(function(){
    function qsAll(sel, root=document){
        return Array.from(root.querySelectorAll(sel));
    }

    function money(n){
        n = Number(n || 0);
        return n.toLocaleString('vi-VN') + ' đ';
    }

    function round2(n){
        return Math.round(Number(n || 0) * 100) / 100;
    }

    function renumberArrayRows(tbody, rowSelector, idxSelector, prefix, removeSelector){
        const rows = qsAll(rowSelector, tbody);

        rows.forEach((row, idx) => {
            const idxCell = row.querySelector(idxSelector);
            if (idxCell) idxCell.textContent = idx + 1;

            qsAll('input, select, textarea', row).forEach(el => {
                if (!el.name) return;
                const re = new RegExp(prefix + '\\\\[\\\\d+\\\\]');
                el.name = el.name.replace(re, prefix + '[' + idx + ']');
            });

            const btnRemove = row.querySelector(removeSelector);
            if (btnRemove) {
                btnRemove.disabled = rows.length === 1;
                btnRemove.title = rows.length === 1 ? 'Không thể xóa khi chỉ còn 1 dòng' : 'Xóa dòng';
            }
        });
    }

    // ===== FINANCE / PAYMENT TERMS =====
    const paymentTermsTbody = document.getElementById('paymentTermsTbody');
    const btnAddPaymentTerm = document.getElementById('btnAddPaymentTerm');
    const contractInput = document.getElementById('contract_amount');

    function calcFinance(){
        const contract = Number(contractInput?.value || 0);
        let totalTerms = 0;
        let totalPercent = 0;

        qsAll('.payment-term-row', paymentTermsTbody).forEach(row => {
            const percentEl = row.querySelector('.term-percent');
            const amountEl = row.querySelector('.term-amount');

            const percent = Number(percentEl?.value || 0);
            let amount = Number(amountEl?.value || 0);

            if (contract > 0 && percent > 0) {
                amount = Math.round(contract * percent / 100);
                if (amountEl) amountEl.value = amount;
            }

            totalPercent += percent;
            totalTerms += amount;
        });

        const diff = totalTerms - contract;
        const progress = contract > 0 ? Math.min(100, Math.max(0, (totalTerms / contract) * 100)) : 0;

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        setText('summaryContract', money(contract));
        setText('summaryTerms', money(totalTerms));
        setText('summaryDiff', money(diff));
        setText('summaryTermCount', String(qsAll('.payment-term-row', paymentTermsTbody).length));
        setText('summaryPercent', String(round2(totalPercent)));

        setText('financeContractMini', money(contract));
        setText('financeTermsMini', money(totalTerms));
        setText('financeDiffMini', money(diff));
        setText('financeTermCountMini', String(qsAll('.payment-term-row', paymentTermsTbody).length));

        const bar = document.getElementById('paymentProgressBar');
        if (bar) {
            bar.style.width = progress + '%';

            if (diff === 0 && contract > 0) {
                bar.classList.add('ok');
                bar.classList.remove('warn');
            } else {
                bar.classList.add('warn');
                bar.classList.remove('ok');
            }
        }

        const diffEls = [
            document.getElementById('summaryDiff'),
            document.getElementById('financeDiffMini')
        ];

        diffEls.forEach(el => {
            if (!el) return;

            el.classList.remove('text-success', 'text-danger', 'text-warning');

            if (contract <= 0 && totalTerms <= 0) {
                return;
            }

            if (diff === 0) {
                el.classList.add('text-success');
            } else {
                el.classList.add('text-danger');
            }
        });
    }

    function createPaymentTermRow(){
        const tpl = paymentTermsTbody.querySelector('.payment-term-row');
        const clone = tpl.cloneNode(true);

        qsAll('input', clone).forEach(input => {
            input.value = '';
        });

        const btnRemove = clone.querySelector('.btnRemoveTerm');
        if (btnRemove) btnRemove.disabled = false;

        return clone;
    }

    btnAddPaymentTerm?.addEventListener('click', () => {
        paymentTermsTbody.appendChild(createPaymentTermRow());
        renumberArrayRows(paymentTermsTbody, '.payment-term-row', '.term-idx', 'payment_terms', '.btnRemoveTerm');
        calcFinance();
    });

    paymentTermsTbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btnRemoveTerm');
        if (!btn) return;

        const rows = qsAll('.payment-term-row', paymentTermsTbody);
        if (rows.length <= 1) return;

        btn.closest('.payment-term-row')?.remove();

        renumberArrayRows(paymentTermsTbody, '.payment-term-row', '.term-idx', 'payment_terms', '.btnRemoveTerm');
        calcFinance();
    });

    paymentTermsTbody?.addEventListener('input', calcFinance);
    contractInput?.addEventListener('input', calcFinance);

    // ===== DEVICES =====
    const devicesTbody = document.getElementById('devicesTbody');
    const btnAddDevice = document.getElementById('btnAddDevice');

    const elCount = document.getElementById('deviceCount');
    const elSumInv = document.getElementById('sumInvKw');
    const elSumBat = document.getElementById('sumBatKwh');
    const elSumPv = document.getElementById('sumPvKwp');

    function setUnitUI(row){
        const type = (row.querySelector('.device-type')?.value || '').toLowerCase();
        const kwEl = row.querySelector('.device-kw');
        const kwhEl = row.querySelector('.device-kwh');

        if (!kwEl || !kwhEl) return;

        if (type === 'battery') {
            kwEl.placeholder = '—';
            kwEl.disabled = true;
            kwEl.value = '';
            kwhEl.placeholder = 'kWh';
            kwhEl.disabled = false;
        } else if (type === 'solar_panel' || type === 'pv') {
            kwEl.placeholder = 'kWp';
            kwEl.disabled = false;
            kwhEl.placeholder = '—';
            kwhEl.disabled = true;
            kwhEl.value = '';
        } else {
            kwEl.placeholder = 'kW';
            kwEl.disabled = false;
            kwhEl.placeholder = '—';
            kwhEl.disabled = true;
            kwhEl.value = '';
        }
    }

    function renumberDevices(){
        renumberArrayRows(devicesTbody, 'tr.device-row', '.device-idx', 'devices', '.btnRemoveDevice');

        qsAll('tr.device-row', devicesTbody).forEach(row => {
            setUnitUI(row);
        });

        calcDeviceSummary();
    }

    function calcDeviceSummary(){
        const rows = qsAll('tr.device-row', devicesTbody);
        if (elCount) elCount.textContent = rows.length;

        let invKw = 0;
        let batKwh = 0;
        let pvKwp = 0;

        rows.forEach(row => {
            const type = (row.querySelector('.device-type')?.value || '').toLowerCase();
            const kw = parseFloat(row.querySelector('input[name*="[power_kw]"]')?.value || '0') || 0;
            const kwh = parseFloat(row.querySelector('input[name*="[capacity_kwh]"]')?.value || '0') || 0;
            const qty = parseFloat(row.querySelector('input[name*="[qty]"]')?.value || '1') || 1;

            if (type.includes('inverter')) invKw += kw * qty;
            if (type === 'battery') batKwh += kwh * qty;
            if (type === 'solar_panel' || type === 'pv') pvKwp += kw * qty;
        });

        if (elSumInv) elSumInv.textContent = invKw > 0 ? round2(invKw) : '—';
        if (elSumBat) elSumBat.textContent = batKwh > 0 ? round2(batKwh) : '—';
        if (elSumPv) elSumPv.textContent = pvKwp > 0 ? round2(pvKwp) : '—';
    }

    function createDeviceRow(){
        const tpl = devicesTbody.querySelector('tr.device-row');
        const clone = tpl.cloneNode(true);

        qsAll('input', clone).forEach(i => {
            if (i.type === 'number') {
                i.value = i.name.includes('[qty]') ? 1 : '';
            } else if (i.type === 'date') {
                i.value = '';
            } else {
                i.value = '';
            }
        });

        qsAll('select', clone).forEach(s => {
            s.selectedIndex = 0;
        });

        const btnRemove = clone.querySelector('.btnRemoveDevice');
        if (btnRemove) btnRemove.disabled = false;

        return clone;
    }

    btnAddDevice?.addEventListener('click', () => {
        devicesTbody.appendChild(createDeviceRow());
        renumberDevices();
    });

    devicesTbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btnRemoveDevice');
        if (!btn) return;

        const rows = qsAll('tr.device-row', devicesTbody);
        if (rows.length <= 1) return;

        btn.closest('tr.device-row')?.remove();
        renumberDevices();
    });

    devicesTbody?.addEventListener('input', e => {
        if (e.target.matches('input, select')) calcDeviceSummary();
    });

    devicesTbody?.addEventListener('change', e => {
        if (e.target.matches('select')) {
            setUnitUI(e.target.closest('tr.device-row'));
            calcDeviceSummary();
        }
    });

    // ===== PLANNED MATERIALS =====
    const plansTbody = document.getElementById('plansTbody');
    const btnAddPlan = document.getElementById('btnAddPlan');
    const elPlanCount = document.getElementById('planCount');

    function renumberPlans(){
        renumberArrayRows(plansTbody, 'tr.plan-row', '.plan-idx', 'planned', '.btnRemovePlan');

        const rows = qsAll('tr.plan-row', plansTbody);
        if (elPlanCount) elPlanCount.textContent = rows.length;
    }

    function createPlanRow(){
        const tpl = plansTbody.querySelector('tr.plan-row');
        const clone = tpl.cloneNode(true);

        qsAll('input', clone).forEach(i => {
            if (i.type === 'number') i.value = 0;
            else i.value = '';
        });

        const btnRemove = clone.querySelector('.btnRemovePlan');
        if (btnRemove) btnRemove.disabled = false;

        return clone;
    }

    btnAddPlan?.addEventListener('click', () => {
        plansTbody.appendChild(createPlanRow());
        renumberPlans();
    });

    plansTbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btnRemovePlan');
        if (!btn) return;

        const rows = qsAll('tr.plan-row', plansTbody);
        if (rows.length <= 1) return;

        btn.closest('tr.plan-row')?.remove();
        renumberPlans();
    });

    plansTbody?.addEventListener('input', renumberPlans);

    // ===== TECHNICIAN MULTI =====
    const techHidden = document.getElementById('technician_name_hidden');
    const techInput = document.getElementById('techInput');
    const techChips = document.getElementById('techChips');

    function getTechVals(){
        return qsAll('.tech-chip', techChips)
            .map(ch => ch.getAttribute('data-val'))
            .filter(Boolean);
    }

    function syncTechHidden(){
        const vals = getTechVals();
        if (techHidden) techHidden.value = vals.join(', ');
    }

    function addTech(val){
        val = (val || '').trim();
        if (!val) return;

        const existing = getTechVals().map(v => v.toLowerCase());
        if (existing.includes(val.toLowerCase())) return;

        const span = document.createElement('span');
        span.className = 'tech-chip';
        span.setAttribute('data-val', val);
        span.innerHTML = `${val}<button type="button" class="tech-x" aria-label="remove">×</button>`;

        techChips.appendChild(span);
        syncTechHidden();
    }

    techInput?.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTech(techInput.value);
            techInput.value = '';
        }
    });

    techChips?.addEventListener('click', e => {
        const x = e.target.closest('.tech-x');
        if (!x) return;

        x.closest('.tech-chip')?.remove();
        syncTechHidden();
    });

    // ===== INIT =====
    syncTechHidden();
    renumberDevices();
    renumberPlans();
    renumberArrayRows(paymentTermsTbody, '.payment-term-row', '.term-idx', 'payment_terms', '.btnRemoveTerm');
    calcFinance();
})();
</script>

<style>
    .ego-sites-form{
        background: linear-gradient(180deg,
            rgba(11, 201, 170, .12) 0%,
            rgba(11, 201, 170, .07) 20%,
            rgba(255,255,255,0) 72%);
        border-radius: 22px;
        padding-top: 18px;
        padding-bottom: 18px;
    }

    .ego-header{
        margin-top: 6px;
    }

    .page-icon{
        width: 42px;
        height: 42px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(11,201,170,.20), rgba(59,130,246,.13));
        color: #0f766e;
        border: 1px solid rgba(11,201,170,.22);
        box-shadow: 0 12px 26px rgba(2, 44, 34, .08);
    }

    .ego-card{
        background: rgba(255,255,255,.95);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(15, 118, 110, .07) !important;
    }

    .shadow-ego{
        box-shadow: 0 14px 38px rgba(2, 44, 34, 0.08) !important;
    }

    .btn-ego{
        background: linear-gradient(135deg, #0BC9AA, #08b79b);
        border: 0;
        color: #fff;
        border-radius: 14px;
        padding: 11px 14px;
        font-weight: 700;
        box-shadow: 0 10px 22px rgba(11,201,170,.26);
    }

    .btn-ego:hover{
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(11,201,170,.32);
    }

    .btn-outline-ego{
        border-color: rgba(11, 201, 170, .55);
        color: #0f766e;
        background: rgba(11, 201, 170, .10);
        border-radius: 13px;
        font-weight: 600;
    }

    .btn-outline-ego:hover{
        border-color: rgba(11, 201, 170, .75);
        background: rgba(11, 201, 170, .16);
        color: #0f766e;
    }

    .icon-pill{
        width: 36px;
        height: 36px;
        border-radius: 13px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(11,201,170,.12);
        color: #0f766e;
        border: 1px solid rgba(0,0,0,.06);
        flex: 0 0 auto;
    }

    .finance-icon{
        background: linear-gradient(135deg, rgba(11,201,170,.18), rgba(245,158,11,.16));
        color: #0f766e;
    }

    .finance-badge{
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(11,201,170,.10);
        color: #0f766e;
        border: 1px solid rgba(11,201,170,.18);
        font-size: 13px;
        font-weight: 700;
    }

    .form-control,
    .form-select,
    .site-input{
        border-radius: 13px;
        padding-top: .58rem;
        padding-bottom: .58rem;
        border-color: rgba(15,23,42,.12);
    }

    .form-control:focus,
    .form-select:focus,
    .site-input:focus{
        border-color: rgba(11,201,170,.7);
        box-shadow: 0 0 0 .2rem rgba(11,201,170,.12);
    }

    .finance-hero{
        border: 1px solid rgba(11,201,170,.17);
        background:
            radial-gradient(circle at top right, rgba(11,201,170,.16), transparent 34%),
            linear-gradient(135deg, rgba(11,201,170,.07), rgba(255,255,255,.92));
        border-radius: 18px;
        padding: 16px;
    }

    .input-money{
        position: relative;
    }

    .input-money input{
        padding-right: 42px;
        font-size: 18px;
    }

    .input-money span{
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        font-weight: 700;
    }

    .payment-terms-wrap{
        border: 1px solid rgba(15,23,42,.06);
        border-radius: 16px;
        overflow: auto;
    }

    .payment-terms-table thead th,
    .ego-table thead th{
        background: #f8fafc;
        color: #334155;
        font-size: .85rem;
        white-space: nowrap;
        vertical-align: middle;
    }

    .payment-terms-table tbody td,
    .ego-table tbody td{
        padding-top: .8rem;
        padding-bottom: .8rem;
        vertical-align: middle;
    }

    .ego-table tbody tr:hover,
    .payment-terms-table tbody tr:hover{
        background: rgba(11,201,170,.055);
    }

    .finance-mini-summary{
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .mini-box{
        border: 1px solid rgba(15,23,42,.07);
        background: rgba(248,250,252,.88);
        border-radius: 15px;
        padding: 12px;
    }

    .finance-summary-main{
        border-radius: 18px;
        padding: 16px;
        background:
            radial-gradient(circle at top right, rgba(11,201,170,.22), transparent 35%),
            linear-gradient(135deg, rgba(11,201,170,.12), rgba(255,255,255,.92));
        border: 1px solid rgba(11,201,170,.16);
    }

    .amount-big{
        font-size: 28px;
        font-weight: 800;
        color: #0f766e;
        letter-spacing: -.5px;
    }

    .summary-grid{
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .summary-item{
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(11, 201, 170, .06);
        border-radius: 15px;
        padding: 12px;
    }

    .summary-item .unit{
        font-size: .85rem;
        color: #64748b;
        font-weight: 600;
    }

    .summary-hint{
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.88);
        border-radius: 15px;
        padding: 12px;
    }

    .hint-ic{
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display:flex;
        align-items:center;
        justify-content:center;
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(11,201,170,.12);
        color: #0f766e;
        flex: 0 0 auto;
    }

    .finance-progress{
        height: 10px;
        border-radius: 999px;
        background: rgba(15,23,42,.08);
        overflow: hidden;
    }

    .finance-progress .progress-bar{
        border-radius: 999px;
        transition: width .25s ease;
        background: linear-gradient(90deg, #f59e0b, #ef4444);
    }

    .finance-progress .progress-bar.ok{
        background: linear-gradient(90deg, #0BC9AA, #10b981);
    }

    .finance-progress .progress-bar.warn{
        background: linear-gradient(90deg, #f59e0b, #ef4444);
    }

    .ego-table{
        table-layout: auto !important;
    }

    .ego-table th,
    .ego-table td{
        white-space: nowrap;
    }

    .ego-table .form-control,
    .ego-table .form-select,
    .ego-table .site-input{
        min-height: 44px;
        padding: .6rem .75rem;
        font-size: 14px;
    }

    .ego-col-type { min-width: 190px; }
    .ego-col-brand{ min-width: 220px; }
    .ego-col-model{ min-width: 260px; }
    .ego-col-serial{ min-width: 220px; }
    .ego-col-kw  { min-width: 170px; }
    .ego-col-kwh { min-width: 170px; }
    .ego-col-qty { min-width: 120px; }
    .ego-col-date{ min-width: 180px; }
    .ego-col-del { min-width: 90px; }

    #devicesTbody .form-control,
    #devicesTbody .form-select,
    #devicesTbody .site-input{
        width: 100%;
        min-width: 100%;
        box-sizing: border-box;
    }

    #devicesTbody .device-type{
        min-width: 190px;
        height: 42px;
    }

    #devicesTbody input[name*="[power_kw]"],
    #devicesTbody input[name*="[capacity_kwh]"],
    #devicesTbody input[name*="[qty]"]{
        min-width: 110px;
        height: 42px;
        padding-right: 12px;
    }

    #devicesTbody input[type="date"]{
        min-width: 160px;
        height: 42px;
    }

    #devicesTbody .btnRemoveDevice,
    #plansTbody .btnRemovePlan,
    #paymentTermsTbody .btnRemoveTerm{
        width: 42px;
        height: 42px;
        border-radius: 12px;
    }

    .tech-box{
        border-radius: 14px;
    }

    .tech-chips{
        display:flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 8px;
    }

    .tech-chip{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        border-radius: 999px;
        padding: 6px 10px;
        background: rgba(11,201,170,.10);
        border: 1px solid rgba(11,201,170,.22);
        color: #0f766e;
        font-size: 13px;
        line-height: 1;
    }

    .tech-x{
        width: 20px;
        height: 20px;
        border-radius: 999px;
        border: 0;
        background: rgba(0,0,0,.06);
        color: #0f766e;
        cursor: pointer;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding: 0;
    }

    .tech-x:hover{
        background: rgba(0,0,0,.10);
    }

    .tech-input{
        min-height: 44px;
    }

    @media (max-width: 991.98px){
        .finance-mini-summary{
            grid-template-columns: 1fr 1fr;
        }

        .sticky-top{
            position: static !important;
        }
    }

    @media (max-width: 575.98px){
        .summary-grid,
        .finance-mini-summary{
            grid-template-columns: 1fr;
        }

        .amount-big{
            font-size: 23px;
        }
    }
</style>
@endsection