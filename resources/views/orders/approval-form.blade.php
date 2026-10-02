
@extends('layouts.app')
@use('Carbon\Carbon')
@section('title', 'Duyệt đơn hàng #' . $order->order_code)

@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="container-fluid tw:px-6 tw:py-4">
        <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
            <h1 class="tw:font-bold tw:uppercase tw:text-[#6c757d]">DUYỆT ĐƠN HÀNG #{{ $order->order_code }}</h1>
            <x-ui.button href="{{ route('orders.show', $order->id) }}" variant="outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </x-ui.button>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <x-ui.alert variant="danger" :dismissible="true">
                <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Có lỗi xảy ra!</h5>
                <ul class="tw:mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="tw:row">
            {{-- Left: Form --}}
            <div class="tw:md:col12-8">
                <x-ui.card class="shadow-sm">
                    <x-ui.card-header class="bg-primary tw:text-[#ffffff]">
                        <h5 class="tw:mb-0"><i class="bi bi-check-circle"></i> Xử lý duyệt đơn hàng</h5>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <form action="{{ route('orders.process-approval', $order->id) }}" method="POST" id="approvalForm">
                            @csrf

                            {{-- Current info --}}
                            <x-ui.alert variant="info">
                                <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Thông tin duyệt</h6>
                                <p class="tw:mb-0">
                                    Bạn đang xử lý duyệt cho cấp: <strong>{{ $levelLabel }}</strong><br>
                                    Trạng thái hiện tại: <strong>{{ $order->currentStatusType->name }}</strong>
                                </p>
                            </x-ui.alert>

                            {{-- Order details --}}
                            {{-- ✅ UI UPDATED: card header đẹp hơn, table đẹp hơn (giữ nguyên dữ liệu) --}}
                            <x-ui.card class="shadow-sm tw:mb-4 border-0">
                                <x-ui.card-header class="bg-white tw:flex tw:justify-between tw:items-center tw:py-4">
                                    <h6 class="tw:mb-0 tw:font-bold tw:uppercase tw:text-[#6c757d]">
                                        <i class="bi bi-box-seam me-1"></i> Chi tiết đơn hàng
                                    </h6>
                                    <span class="badge bg-secondary">{{ $order->items->count() }} mặt hàng</span>
                                </x-ui.card-header>

                                <x-ui.card-body>
                                    <div class="tw:row tw:mb-2">
                                        <div class="tw:md:col12-6">
                                            <p class="tw:mb-1"><strong>Khách hàng:</strong> {{ $order->lead->customer->name }}</p>
                                            <p class="tw:mb-1"><strong>Điện thoại:</strong> {{ $order->lead->customer->phone }}</p>
                                            <p class="tw:mb-1"><strong>Loại khách:</strong> {{ $order->lead->customer->customerType->name ?? 'N/A' }}</p>
                                        </div>
                                        <div class="tw:md:col12-6">
                                            <p class="tw:mb-1"><strong>Ngày đặt:</strong> {{ $order->order_date ? Carbon::parse($order->order_date)->format('d/m/Y') : '-' }}</p>

                                            <p class="tw:mb-1"><strong>Kho xuất:</strong> {{ $warehouseText }}</p>
                                            <p class="tw:mb-1"><strong>Sales:</strong> {{ $order->creator->name ?? 'N/A' }}</p>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        {{-- ✅ UI UPDATED: bỏ table-bordered, dùng hover + align-middle --}}
                                        <table class="table table-hover align-middle tw:mb-0">
                                            <thead class="table-light">
                                            <tr class="small tw:uppercase tw:text-[#6c757d]">
                                                <th style="min-width:260px;">Sản phẩm</th>
                                                <th class="tw:text-center" style="width:70px;">SL</th>
                                                <th class="text-end" style="min-width:130px;">Đơn giá</th>
                                                <th class="tw:text-center" style="min-width:110px;">Giảm (%)</th>
                                                <th class="text-end" style="min-width:140px;">Giảm (đ)</th>
                                                <th class="text-end" style="min-width:150px;">Thành tiền</th>
                                            </tr>
                                            </thead>

                                            <tbody>
                                            @foreach($itemViews as $item)

                                                <tr>
                                                    <td class="tw:font-semibold">{{ $item->productName }}</td>
                                                    <td class="tw:text-center">
                                                        <span class="badge bg-light tw:text-[#212529] border">{{ $item->qty }}</span>
                                                    </td>
                                                    <td class="text-end">{{ number_format($item->unitPrice, 0, ',', '.') }} đ</td>
                                                    <td class="tw:text-center">
                                                        @if($item->discountPercent > 0)
                                                            <span class="badge bg-warning tw:text-[#212529]">
                                                                {{ rtrim(rtrim(number_format($item->discountPercent, 2), '0'), '.') }}%
                                                            </span>
                                                        @else
                                                            <span class="tw:text-[rgba(33,37,41,0.75)]">0%</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if($item->discountAmount > 0)
                                                            <span class="text-danger tw:font-semibold">
                                                                -{{ number_format($item->discountAmount, 0, ',', '.') }} đ
                                                            </span>
                                                        @else
                                                            <span class="tw:text-[rgba(33,37,41,0.75)]">0 đ</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end tw:font-bold">{{ number_format($item->lineTotal, 0, ',', '.') }} đ</td>
                                                </tr>
                                            @endforeach
                                            </tbody>

                                            <tfoot class="table-light">
                                            <tr>
                                                {{-- ✅ FIX: vì table có 6 cột => colspan 5, tổng nằm cột cuối --}}
                                                <td colspan="5" class="text-end tw:font-bold">TỔNG CỘNG:</td>
                                                <td class="text-end tw:font-bold tw:text-[#0d6efd]">{{ number_format($computedTotal, 0, ',', '.') }} đ</td>
                                            </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </x-ui.card-body>
                            </x-ui.card>

                            {{-- Debt check: Accounting only --}}
                            @if($isAccounting)
                                <x-ui.card class="tw:mb-4 border-warning">
                                    <x-ui.card-header class="bg-warning">
                                        <h6 class="tw:mb-0"><i class="bi bi-exclamation-triangle"></i> Kiểm tra công nợ</h6>
                                    </x-ui.card-header>
                                    <x-ui.card-body>

                                        <x-ui.alert :variant="$remainingDebt > 0 ? 'danger' : 'success'">
                                            <strong>Tổng công nợ hiện tại:</strong>
                                            <span class="fs-5">{{ number_format($remainingDebt, 0, ',', '.') }} đ</span>
                                        </x-ui.alert>

                                        <div class="form-check tw:mb-2">
                                            <input class="form-check-input @error('debt_checked') is-invalid @enderror"
                                                   type="checkbox"
                                                   name="debt_checked"
                                                   id="debtChecked"
                                                   value="1"
                                                    {{ old('debt_checked') ? 'checked' : '' }}>
                                            <label class="form-check-label tw:font-bold" for="debtChecked">
                                                Tôi đã kiểm tra công nợ của khách hàng <span class="text-danger">*</span>
                                            </label>
                                            @error('debt_checked')
                                            <div class="invalid-feedback tw:block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="tw:mb-4">
                                            <x-ui.label>Ghi chú về công nợ (nếu có)</x-ui.label>
                                            <x-ui.input as="textarea" name="debt_note"
                                                      class="@error('debt_note') is-invalid @enderror"
                                                      rows="2"
                                                      placeholder="VD: Khách có công nợ cũ nhưng đã cam kết thanh toán...">{{ old('debt_note') }}</x-ui.input>
                                            @error('debt_note')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </x-ui.card-body>
                                </x-ui.card>
                            @endif

                           {{-- Approval history --}}
<x-ui.card class="tw:mb-4">
    <x-ui.card-header class="bg-light">
        <h6 class="tw:mb-0"><i class="bi bi-clock-history"></i> Lịch sử duyệt</h6>
    </x-ui.card-header>
    <x-ui.card-body>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>Cấp duyệt</th>
                    <th>Người duyệt</th>
                    <th>Thời gian</th>
                    <th>Trạng thái</th>
                </tr>
                </thead>
                <tbody>
                @foreach($approvalRows as $row)

                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ $row['approverName'] }}</td>
                        <td>{{ $row['displayTime'] }}</td>
                        <td>
                            <span class="badge {{ $row['badgeClass'] }}">{{ $row['statusText'] }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card-body>
</x-ui.card>

                            {{-- Decision --}}
                            <x-ui.card class="tw:mb-4 border-primary">
                                <x-ui.card-header class="bg-primary tw:text-[#ffffff]">
                                    <h6 class="tw:mb-0"><i class="bi bi-pencil-square"></i> Quyết định của bạn</h6>
                                </x-ui.card-header>
                                <x-ui.card-body>
                                    <div class="tw:mb-4">
                                        <x-ui.label class="tw:font-bold">Chọn hành động <span class="text-danger">*</span></x-ui.label>
                                        <div class="tw:flex tw:gap-4">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input @error('action') is-invalid @enderror"
                                                       type="radio"
                                                       name="action"
                                                       id="actionApprove"
                                                       value="approve"
                                                       {{ old('action') === 'approve' ? 'checked' : '' }}
                                                       required>
                                                <label class="form-check-label text-success tw:font-bold" for="actionApprove">
                                                    <i class="bi bi-check-circle"></i> Duyệt đơn
                                                </label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input @error('action') is-invalid @enderror"
                                                       type="radio"
                                                       name="action"
                                                       id="actionReject"
                                                       value="reject"
                                                       {{ old('action') === 'reject' ? 'checked' : '' }}
                                                       required>
                                                <label class="form-check-label text-danger tw:font-bold" for="actionReject">
                                                    <i class="bi bi-x-circle"></i> Từ chối
                                                </label>
                                            </div>
                                        </div>

                                        @error('action')
                                        <div class="text-danger small tw:mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="tw:mb-4" id="rejectionReasonGroup" style="display: {{ old('action') === 'reject' ? 'block' : 'none' }};">
                                        <x-ui.label class="tw:font-bold">Lý do từ chối <span class="text-danger">*</span></x-ui.label>
                                        <x-ui.input as="textarea" name="rejection_reason"
                                                  id="rejectionReason"
                                                  class="@error('rejection_reason') is-invalid @enderror"
                                                  rows="3"
                                                  placeholder="Nhập lý do từ chối đơn hàng...">{{ old('rejection_reason') }}</x-ui.input>
                                        @error('rejection_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Lý do này sẽ được gửi cho Sales</div>
                                    </div>

                                    <div class="tw:mb-4">
                                        <x-ui.label>Ghi chú thêm (không bắt buộc)</x-ui.label>
                                        <x-ui.input as="textarea" name="note"
                                                  class="@error('note') is-invalid @enderror"
                                                  rows="2"
                                                  placeholder="Ghi chú thêm về quyết định của bạn...">{{ old('note') }}</x-ui.input>
                                        @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <x-ui.alert variant="warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <strong>Lưu ý:</strong> Quyết định của bạn sẽ ảnh hưởng trực tiếp đến quy trình xử lý đơn hàng.
                                        Vui lòng kiểm tra kỹ trước khi xác nhận.
                                    </x-ui.alert>
                                </x-ui.card-body>
                            </x-ui.card>

                            {{-- Actions --}}
                            <div class="tw:flex tw:justify-end tw:gap-2">
                                <x-ui.button href="{{ route('orders.show', $order->id) }}" variant="secondary">
                                    <i class="bi bi-x"></i> Hủy
                                </x-ui.button>
                                <x-ui.button variant="success" size="lg" type="submit" id="submitBtn">
                                    <i class="bi bi-check-circle"></i> Xác nhận duyệt
                                </x-ui.button>
                            </div>
                        </form>
                    </x-ui.card-body>
                </x-ui.card>
            </div>

            {{-- Right: Sidebar --}}
            <div class="tw:md:col12-4">
                <x-ui.card class="shadow-sm tw:mb-4">
                    <x-ui.card-header class="bg-dark tw:text-[#ffffff]">
                        <h5 class="tw:mb-0"><i class="bi bi-info-circle"></i> Tóm tắt</h5>
                    </x-ui.card-header>
                    <x-ui.card-body>
                        <table class="table table-sm">
                            <tr>
                                <td>Tổng sản phẩm:</td>
                                <td class="text-end tw:font-bold">{{ $order->items->count() }}</td>
                            </tr>
                            <tr>
                                <td>Tổng số lượng:</td>
                                <td class="text-end tw:font-bold">{{ $order->items->sum('quantity') }}</td>
                            </tr>
                            <tr class="table-primary">
                                <td>Tổng tiền:</td>
                                {{-- ✅ FIX: dùng tổng theo line_total --}}
                                <td class="text-end tw:font-bold fs-5">{{ number_format($computedTotal, 0, ',', '.') }} đ</td>
                            </tr>
                        </table>
                    </x-ui.card-body>
                </x-ui.card>

                <x-ui.card class="shadow-sm border-info">
                    <x-ui.card-header class="bg-info tw:text-[#ffffff]">
                        <h5 class="tw:mb-0"><i class="bi bi-lightbulb"></i> Hướng dẫn</h5>
                    </x-ui.card-header>
                    <x-ui.card-body>
                        <h6 class="tw:text-[#0dcaf0]">Trách nhiệm của {{ $levelLabel }}:</h6>
                        <ul class="small">
                            @if($isAccounting)
                                <li>Kiểm tra công nợ khách hàng</li>
                                <li>Xác minh thông tin thanh toán</li>
                                <li>Đảm bảo đơn hàng hợp lệ</li>
                            @elseif($dept === 'sales_manager')
                                <li>Kiểm tra tính hợp lý của đơn hàng</li>
                                <li>Xác nhận số lượng và giá trị</li>
                                <li>Đánh giá rủi ro</li>
                            @elseif($dept === 'management')
                                <li>Duyệt cuối cùng trước khi xuất kho</li>
                                <li>Quyết định chiến lược</li>
                                <li>Phê duyệt đơn hàng lớn</li>
                            @elseif($dept === 'warehouse')
                                <li>Chuẩn bị hàng hóa</li>
                                <li>Xuất kho theo đơn</li>
                                <li>Hoàn tất giao hàng</li>
                            @endif
                        </ul>
                        <hr>
                        <p class="small tw:mb-0 tw:text-[rgba(33,37,41,0.75)]">
                            <i class="bi bi-clock"></i> Sau khi duyệt, đơn hàng sẽ chuyển sang bộ phận tiếp theo trong quy trình.
                        </p>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
        </div>
    </div>

    {{-- JS: only one block --}}
    <script>
        const isAccounting = @json($isAccounting);

        function syncUIByAction() {
            const approve = document.getElementById('actionApprove');
            const reject = document.getElementById('actionReject');
            const rejectionGroup = document.getElementById('rejectionReasonGroup');
            const rejectionInput = document.getElementById('rejectionReason');
            const submitBtn = document.getElementById('submitBtn');

            if (reject && reject.checked) {
                rejectionGroup.style.display = 'block';
                rejectionInput.required = true;
                submitBtn.innerHTML = '<i class="bi bi-x-circle"></i> Xác nhận từ chối';
                submitBtn.className = 'btn btn-danger btn-lg';
                return;
            }

            // default approve
            rejectionGroup.style.display = 'none';
            rejectionInput.required = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Xác nhận duyệt';
            submitBtn.className = 'btn btn-success btn-lg';
        }

        document.addEventListener('DOMContentLoaded', function () {
            syncUIByAction();

            document.getElementById('actionApprove')?.addEventListener('change', syncUIByAction);
            document.getElementById('actionReject')?.addEventListener('change', syncUIByAction);

            document.getElementById('approvalForm').addEventListener('submit', function (e) {
                const action = document.querySelector('input[name="action"]:checked');
                if (!action) {
                    e.preventDefault();
                    alert('Vui lòng chọn hành động (Duyệt hoặc Từ chối)');
                    return;
                }

                // Require debt_checked for accounting when approve
                if (isAccounting && action.value === 'approve') {
                    const debtCheckbox = document.getElementById('debtChecked');
                    if (!debtCheckbox || !debtCheckbox.checked) {
                        e.preventDefault();
                        alert('Bạn phải xác nhận đã kiểm tra công nợ.');
                        debtCheckbox?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                }

                // Require rejection reason
                if (action.value === 'reject') {
                    const reason = document.getElementById('rejectionReason')?.value?.trim();
                    if (!reason) {
                        e.preventDefault();
                        alert('Vui lòng nhập lý do từ chối.');
                        document.getElementById('rejectionReason')?.focus();
                        return;
                    }
                }

                const actionText = action.value === 'approve' ? 'DUYỆT' : 'TỪ CHỐI';
                if (!confirm(`Bạn có chắc chắn muốn ${actionText} đơn hàng này?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endsection
