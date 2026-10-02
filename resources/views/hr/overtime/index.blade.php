@extends('layouts.app')

@section('content')
{{-- Lưới dùng `tw:row` / `tw:col12-*` / `tw:g-*`: đó là @utility tự khai trong resources/css/app.css,
     tái lập đúng mô hình lưới của Bootstrap (lề âm trên hàng + đệm trên con), KHÔNG phải grid+gap.
     Breakpoint dùng min-[75rem] để khớp mốc xl của Bootstrap (1200px) thay vì xl: của Tailwind. --}}
<div class="tw:w-full tw:px-3 tw:mx-auto tw:py-6">
    <div class="tw:flex tw:justify-between tw:items-center tw:flex-wrap tw:gap-2 tw:mb-6">
        <div>
            <h3 class="tw:mb-1 tw:font-bold">Đăng ký tăng ca</h3>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Theo dõi đơn tăng ca và duyệt để ghi chú vào bảng chấm công</div>
        </div>

        <x-ui.button href="{{ route('hr.overtime.create') }}" variant="primary" class="tw:rounded-[50rem]! tw:px-6">
            <i class="bi bi-plus-circle tw:mr-1"></i> Tạo đơn tăng ca
        </x-ui.button>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success" class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)]">{{ session('success') }}</x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)]">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="tw:row tw:g-3 tw:mb-4">
        <div class="tw:col12-6 tw:min-[75rem]:col12">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem]"><x-ui.card-body>
                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:font-bold">Tổng đơn</div>
                <div class="tw:text-[calc(1.3rem+0.6vw)] tw:min-[75rem]:text-[1.75rem] tw:font-bold">{{ $summaryCards->totalText }}</div>
            </x-ui.card-body></x-ui.card>
        </div>

        <div class="tw:col12-6 tw:min-[75rem]:col12">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem]"><x-ui.card-body>
                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:font-bold">Chờ duyệt</div>
                <div class="tw:text-[calc(1.3rem+0.6vw)] tw:min-[75rem]:text-[1.75rem] tw:font-bold">{{ $summaryCards->pendingText }}</div>
            </x-ui.card-body></x-ui.card>
        </div>

        <div class="tw:col12-6 tw:min-[75rem]:col12">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem]"><x-ui.card-body>
                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:font-bold">Đã duyệt</div>
                <div class="tw:text-[calc(1.3rem+0.6vw)] tw:min-[75rem]:text-[1.75rem] tw:font-bold">{{ $summaryCards->approvedText }}</div>
            </x-ui.card-body></x-ui.card>
        </div>

        <div class="tw:col12-6 tw:min-[75rem]:col12">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem]"><x-ui.card-body>
                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:font-bold">Từ chối</div>
                <div class="tw:text-[calc(1.3rem+0.6vw)] tw:min-[75rem]:text-[1.75rem] tw:font-bold">{{ $summaryCards->rejectedText }}</div>
            </x-ui.card-body></x-ui.card>
        </div>

        <div class="tw:col12-12 tw:min-[75rem]:col12">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem]"><x-ui.card-body>
                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:font-bold">Giờ tăng ca duyệt</div>
                <div class="tw:text-[calc(1.3rem+0.6vw)] tw:min-[75rem]:text-[1.75rem] tw:font-bold">{{ $summaryCards->hoursText }} giờ</div>
            </x-ui.card-body></x-ui.card>
        </div>
    </div>

    <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem] tw:mb-4">
        <x-ui.card-body>
            <form method="GET" class="tw:row tw:g-3 tw:items-end">
                <div class="tw:md:col12-3">
                    <x-ui.label class="tw:font-semibold">Tháng</x-ui.label>
                    <x-ui.input type="month" name="month" value="{{ $month }}" class="tw:rounded-[1rem]!" />
                </div>

                <div class="tw:md:col12-3">
                    <x-ui.label class="tw:font-semibold">Trạng thái</x-ui.label>
                    <x-ui.select name="status" class="tw:rounded-[1rem]!">
                        <option value="">-- Tất cả --</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Từ chối</option>
                    </x-ui.select>
                </div>

                @if($canManage)
                    <div class="tw:md:col12-4">
                        <x-ui.label class="tw:font-semibold">Nhân viên</x-ui.label>
                        <x-ui.select name="user_id" class="tw:rounded-[1rem]!">
                            <option value="">-- Tất cả --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (int)$userId === (int)$employee->id ? 'selected' : '' }}>
                                    {{ $employee->name }} @if(optional($employee->department)->name) - {{ optional($employee->department)->name }} @endif
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                @endif

                <div class="tw:md:col12-2 tw:flex tw:gap-2">
                    <x-ui.button variant="dark" type="submit" class="tw:rounded-[1rem]! tw:px-6 tw:w-full">Lọc</x-ui.button>
                    <x-ui.button href="{{ route('hr.overtime.index') }}" variant="light" class="tw:rounded-[1rem]! tw:px-4">Xóa</x-ui.button>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>

    <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
        <x-ui.table-wrap>
            <x-ui.table hover class="tw:align-middle tw:mb-0">
                <x-ui.table-head>
                    <tr>
                        <th scope="col">Nhân viên</th>
                        <th scope="col">Ngày</th>
                        <th scope="col">Thời gian</th>
                        <th scope="col">Số giờ</th>
                        <th scope="col">Người duyệt</th>
                        <th scope="col">Lý do</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col" class="tw:w-[240px]">Thao tác</th>
                    </tr>
                </x-ui.table-head>
                <tbody>
                    @forelse($overtimeRows as $row)
                        <tr>
                            <td>
                                <div class="tw:font-bold">{{ $row->userName }}</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">{{ $row->departmentName }}</div>
                            </td>
                            <td class="tw:font-bold">{{ $row->dateText }}</td>
                            <td>{{ $row->timeText }}</td>
                            <td class="tw:font-bold">{{ $row->hoursText }} giờ</td>
                            <td>{{ $row->approverName }}</td>
                            <td class="tw:min-w-[260px]">{{ $row->reasonText }}</td>
                            <td>
                                <x-hr.overtime-badge :tone="$row->statusTone">{{ $row->statusLabel }}</x-hr.overtime-badge>
                                @if($row->approvalNote)
                                    <div class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mt-1">{{ $row->approvalNote }}</div>
                                @endif
                            </td>
                            <td>
                                @if($row->canApprove)
                                    <form method="POST" action="{{ route('hr.overtime.approve', $row->id) }}" class="tw:mb-2">
                                        @csrf
                                        <label>
                                            <span class="tw:sr-only">Ghi chú duyệt đơn tăng ca {{ $row->dateText }} của {{ $row->userName }}</span>
                                            <x-ui.input size="sm" type="text" name="approval_note" class="tw:rounded-[0.5rem]! tw:mb-1" placeholder="Ghi chú duyệt nếu có" />
                                        </label>
                                        <x-ui.button variant="success" size="sm" type="submit" class="tw:rounded-[0.5rem]! tw:w-full">Duyệt</x-ui.button>
                                    </form>

                                    <form method="POST" action="{{ route('hr.overtime.reject', $row->id) }}">
                                        @csrf
                                        <label>
                                            <span class="tw:sr-only">Lý do từ chối đơn tăng ca {{ $row->dateText }} của {{ $row->userName }}</span>
                                            <x-ui.input size="sm" type="text" name="approval_note" class="tw:rounded-[0.5rem]! tw:mb-1" placeholder="Lý do từ chối" />
                                        </label>
                                        <x-ui.button variant="danger" size="sm" type="submit" class="tw:rounded-[0.5rem]! tw:w-full">Từ chối</x-ui.button>
                                    </form>
                                @else
                                    <span class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Không có thao tác</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-12">Chưa có đơn tăng ca nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.table-wrap>

        <div class="tw:p-4 tw:[border-top:1px_solid_#dee2e6]">
            {{ $requests->links() }}
        </div>
    </x-ui.card>
</div>
@endsection
