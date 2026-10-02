@extends('layouts.app')

@section('content')
<div class="tw:px-1 tw:pb-9">
    <div class="tw:flex tw:flex-col tw:gap-3 tw:mb-4 tw:md:flex-row tw:md:items-end tw:md:justify-between">
        <div>
            <h1 class="tw:m-0 tw:text-[25px] tw:font-extrabold tw:tracking-tight tw:text-[#0f172a]">Chi tiết nhân viên</h1>
            <div class="tw:mt-1 tw:text-[13px] tw:font-semibold tw:text-[#64748b]">Trang chính chỉ hiển thị thông tin. Nhập liệu nằm trong popup riêng.</div>
        </div>
        <div class="tw:flex tw:flex-wrap tw:gap-2">
            <x-ui.button variant="primary" onclick="openEmpModal('profileModal')">Cập nhật hồ sơ</x-ui.button>
            <x-ui.button onclick="openEmpModal('fileModal')">Upload file</x-ui.button>
            <x-ui.button :href="route('hr.employees.index')">Quay lại</x-ui.button>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success" class="tw:mb-3">{{ session('success') }}</x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert variant="danger" class="tw:mb-3">{{ $errors->first() }}</x-ui.alert>
    @endif

    <x-ui.card class="tw:mb-[14px] tw:rounded-[20px] tw:border-[#e2e8f0] tw:shadow-[0_16px_36px_rgba(15,23,42,.055)]">
        <x-ui.card-body class="tw:flex tw:flex-col tw:gap-4 tw:p-[18px] tw:md:flex-row tw:md:items-center tw:md:justify-between">
            <div class="tw:flex tw:items-center tw:gap-[14px]">
                <div class="tw:flex tw:size-[72px] tw:shrink-0 tw:items-center tw:justify-center tw:rounded-[22px] tw:border tw:border-solid tw:border-[#bfdbfe] tw:bg-[linear-gradient(135deg,#dbeafe,#ccfbf1)] tw:text-[25px] tw:font-extrabold tw:text-[#0f766e]">{{ mb_substr($name, 0, 1) }}</div>
                <div>
                    <div class="tw:mb-2 tw:text-[23px] tw:font-extrabold tw:text-[#0f172a]">{{ $name }}</div>
                    <div class="tw:flex tw:flex-wrap tw:gap-[7px]">
                        <span class="tw:inline-flex tw:h-[27px] tw:items-center tw:rounded-full tw:border tw:border-solid tw:border-[#dbeafe] tw:bg-[#eff6ff] tw:px-[10px] tw:text-[11px] tw:font-extrabold tw:text-[#1d4ed8]">{{ $chipCode }}</span>
                        <span class="tw:inline-flex tw:h-[27px] tw:items-center tw:rounded-full tw:border tw:border-solid tw:border-[#bbf7d0] tw:bg-[#f0fdf4] tw:px-[10px] tw:text-[11px] tw:font-extrabold tw:text-[#15803d]">{{ $chipStatus }}</span>
                        <span class="tw:inline-flex tw:h-[27px] tw:items-center tw:rounded-full tw:border tw:border-solid tw:border-[#fde68a] tw:bg-[#fffbeb] tw:px-[10px] tw:text-[11px] tw:font-extrabold tw:text-[#b45309]">{{ $chipRole }}</span>
                    </div>
                </div>
            </div>
            <div class="tw:flex tw:flex-wrap tw:gap-2">
                <x-ui.button :href="route('hr.employees.edit', $employeeId)">Sửa thông tin chính</x-ui.button>
            </div>
        </x-ui.card-body>

        <div class="tw:grid tw:grid-cols-1 tw:gap-[10px] tw:px-[18px] tw:pb-[18px] tw:sm:grid-cols-2 tw:lg:grid-cols-4">
            <x-hr.employee-fact label="Email" :value="$text['email']" />
            <x-hr.employee-fact label="Số điện thoại" :value="$text['phone']" />
            <x-hr.employee-fact label="Phòng ban" :value="$text['department']" />
            <x-hr.employee-fact label="Chức vụ" :value="$text['position']" />
        </div>
    </x-ui.card>

    <div class="tw:grid tw:grid-cols-1 tw:gap-[14px] tw:lg:grid-cols-[1.15fr_.85fr]">
        <x-ui.card class="tw:rounded-[20px] tw:border-[#e2e8f0] tw:shadow-[0_16px_36px_rgba(15,23,42,.055)]">
            <x-ui.card-body class="tw:px-[18px] tw:py-4">
                <div class="tw:mb-3 tw:flex tw:items-center tw:justify-between tw:gap-[10px]">
                    <div>
                        <h3 class="tw:m-0 tw:text-base tw:font-extrabold tw:text-[#0f172a]">Hồ sơ nhân sự</h3>
                        <div class="tw:mt-[3px] tw:text-xs tw:font-semibold tw:text-[#64748b]">Chỉ hiển thị các thông tin quan trọng.</div>
                    </div>
                    <x-ui.button size="sm" onclick="openEmpModal('profileModal')">Sửa hồ sơ</x-ui.button>
                </div>

                <div class="tw:grid tw:grid-cols-1 tw:gap-[9px] tw:sm:grid-cols-2">
                    @foreach($infoRows as [$label, $value])
                        <x-hr.employee-fact :label="$label" :value="$value" />
                    @endforeach
                    <x-hr.employee-fact label="Địa chỉ" :value="$text['address']" />
                    <x-hr.employee-fact label="Ghi chú nhân sự" :value="$text['hr_note']" />
                </div>
            </x-ui.card-body>
        </x-ui.card>

        <div class="tw:flex tw:flex-col tw:gap-[14px]">
            <x-ui.card class="tw:rounded-[20px] tw:border-[#e2e8f0] tw:shadow-[0_16px_36px_rgba(15,23,42,.055)]">
                <x-ui.card-body class="tw:px-[18px] tw:py-4">
                    <div class="tw:mb-3">
                        <h3 class="tw:m-0 tw:text-base tw:font-extrabold tw:text-[#0f172a]">Mức lương</h3>
                        <div class="tw:mt-[3px] tw:text-xs tw:font-semibold tw:text-[#64748b]">Thông tin lương đang lưu trên hồ sơ.</div>
                    </div>

                    <div class="tw:grid tw:grid-cols-1 tw:gap-[9px]">
                        <x-hr.employee-fact label="Lương chính thức" :value="$salaryText['official_salary']" />
                        <x-hr.employee-fact label="Lương thử việc" :value="$salaryText['probation_salary']" />
                        <x-hr.employee-fact label="Lương thực tập" :value="$salaryText['intern_salary']" />
                    </div>
                </x-ui.card-body>
            </x-ui.card>

            <x-ui.card class="tw:rounded-[20px] tw:border-[#e2e8f0] tw:shadow-[0_16px_36px_rgba(15,23,42,.055)]">
                <x-ui.card-body class="tw:px-[18px] tw:py-4">
                    <div class="tw:mb-3 tw:flex tw:items-center tw:justify-between tw:gap-[10px]">
                        <div>
                            <h3 class="tw:m-0 tw:text-base tw:font-extrabold tw:text-[#0f172a]">File hồ sơ</h3>
                            <div class="tw:mt-[3px] tw:text-xs tw:font-semibold tw:text-[#64748b]">{{ $fileCount }} file đã upload.</div>
                        </div>
                        <x-ui.button size="sm" onclick="openEmpModal('fileModal')">+ File</x-ui.button>
                    </div>

                    @if($fileCount)
                        <div class="tw:flex tw:flex-col tw:gap-2">
                            @foreach($fileRows as $file)
                                <div class="tw:flex tw:flex-col tw:gap-[10px] tw:rounded-[14px] tw:border tw:border-solid tw:border-[#e2e8f0] tw:bg-[#f8fafc] tw:px-[11px] tw:py-[10px] tw:sm:flex-row tw:sm:items-center tw:sm:justify-between">
                                    <div>
                                        <div class="tw:text-[13px] tw:font-extrabold tw:text-[#0f172a]">{{ $file->name }}</div>
                                        <div class="tw:mt-[3px] tw:text-[11px] tw:font-semibold tw:text-[#64748b]">
                                            {{ $file->typeLabel }}
                                            · {{ $file->sizeText }}
                                            · {{ $file->uploadedAtText }}
                                        </div>
                                    </div>
                                    <div class="tw:flex tw:flex-wrap tw:gap-2">
                                        <x-ui.button size="sm" :href="route('hr.employees.files.download', $file->id)">Tải</x-ui.button>
                                        <form method="POST" action="{{ route('hr.employees.files.delete', $file->id) }}" onsubmit="return confirm('Xoá file này?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button variant="outline-danger" size="sm" type="submit">Xoá</x-ui.button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="tw:rounded-[14px] tw:border tw:border-dashed tw:border-[#cbd5e1] tw:bg-[#f8fafc] tw:px-3 tw:py-6 tw:text-center tw:text-xs tw:font-semibold tw:text-[#64748b]">Chưa có file hồ sơ nào.</div>
                    @endif
                </x-ui.card-body>
            </x-ui.card>
        </div>
    </div>
</div>

<x-hr.employee-modal id="profileModal" title="Cập nhật hồ sơ mở rộng">
    <form method="POST" action="{{ route('hr.employees.extras.update', $employeeId) }}">
        @csrf
        @method('PUT')

        <div class="tw:px-[18px] tw:py-4">
            <div class="tw:grid tw:grid-cols-1 tw:gap-[10px] tw:sm:grid-cols-2 tw:lg:grid-cols-3">
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Mã nhân viên</x-ui.label>
                    <x-ui.input name="employee_code" :value="$formValue['employee_code']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Ngày nhận việc</x-ui.label>
                    <x-ui.input type="date" name="hire_date" :value="$dateValue['hire_date']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Ngày chính thức</x-ui.label>
                    <x-ui.input type="date" name="official_date" :value="$dateValue['official_date']" />
                </div>

                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Bắt đầu thử việc</x-ui.label>
                    <x-ui.input type="date" name="probation_start_date" :value="$dateValue['probation_start_date']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Kết thúc thử việc</x-ui.label>
                    <x-ui.input type="date" name="probation_end_date" :value="$dateValue['probation_end_date']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Loại hợp đồng</x-ui.label>
                    <x-ui.select name="contract_type">
                        @foreach(['Thử việc', 'Chính thức', 'CTV', 'Thực tập', 'Khoán việc', 'Khác'] as $type)
                            <option value="{{ $type }}" @selected($formValue['contract_type'] === $type)>{{ $type }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="tw:flex tw:flex-col">
                    <x-ui.label>HĐ từ ngày</x-ui.label>
                    <x-ui.input type="date" name="contract_start_date" :value="$dateValue['contract_start_date']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>HĐ đến ngày</x-ui.label>
                    <x-ui.input type="date" name="contract_end_date" :value="$dateValue['contract_end_date']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Ngày sinh</x-ui.label>
                    <x-ui.input type="date" name="birth_date" :value="$dateValue['birth_date']" />
                </div>

                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Giới tính</x-ui.label>
                    <x-ui.select name="gender">
                        @foreach(['Nam', 'Nữ', 'Khác'] as $gender)
                            <option value="{{ $gender }}" @selected($formValue['gender'] === $gender)>{{ $gender }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>CCCD/CMND</x-ui.label>
                    <x-ui.input name="id_card" :value="$formValue['id_card']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Ngày cấp CCCD</x-ui.label>
                    <x-ui.input type="date" name="id_card_date" :value="$dateValue['id_card_date']" />
                </div>

                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Nơi cấp CCCD</x-ui.label>
                    <x-ui.input name="id_card_place" :value="$formValue['id_card_place']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Ngân hàng</x-ui.label>
                    <x-ui.input name="bank_name" :value="$formValue['bank_name']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Số tài khoản</x-ui.label>
                    <x-ui.input name="bank_account" :value="$formValue['bank_account']" />
                </div>

                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Mã số thuế</x-ui.label>
                    <x-ui.input name="tax_code" :value="$formValue['tax_code']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Số BHXH</x-ui.label>
                    <x-ui.input name="insurance_number" :value="$formValue['insurance_number']" />
                </div>
                <div class="tw:flex tw:flex-col">
                    <x-ui.label>SĐT khẩn cấp</x-ui.label>
                    <x-ui.input name="emergency_contact_phone" :value="$formValue['emergency_contact_phone']" />
                </div>

                <div class="tw:flex tw:flex-col">
                    <x-ui.label>Liên hệ khẩn cấp</x-ui.label>
                    <x-ui.input name="emergency_contact_name" :value="$formValue['emergency_contact_name']" />
                </div>
                <div class="tw:col-span-full tw:flex tw:flex-col">
                    <x-ui.label>Địa chỉ</x-ui.label>
                    <x-ui.input as="textarea" name="address" class="tw:min-h-[76px] tw:resize-y">{{ $formValue['address'] }}</x-ui.input>
                </div>
                <div class="tw:col-span-full tw:flex tw:flex-col">
                    <x-ui.label>Ghi chú nhân sự</x-ui.label>
                    <x-ui.input as="textarea" name="hr_note" class="tw:min-h-[76px] tw:resize-y">{{ $formValue['hr_note'] }}</x-ui.input>
                </div>
            </div>
        </div>

        <div class="tw:sticky tw:bottom-0 tw:z-[2] tw:flex tw:justify-end tw:gap-2 tw:border-t tw:border-solid tw:border-[#edf2f7] tw:bg-[#f8fafc] tw:px-[18px] tw:py-[14px]">
            <x-ui.button onclick="hideEmpModal('profileModal')">Huỷ</x-ui.button>
            <x-ui.button variant="primary" type="submit">Lưu hồ sơ</x-ui.button>
        </div>
    </form>
</x-hr.employee-modal>

<x-hr.employee-modal id="fileModal" title="Upload file hồ sơ" small>
    <form method="POST" action="{{ route('hr.employees.files.store', $employeeId) }}" enctype="multipart/form-data">
        @csrf
        <div class="tw:px-[18px] tw:py-4">
            <div class="tw:mb-[10px] tw:flex tw:flex-col">
                <x-ui.label>Loại file</x-ui.label>
                <x-ui.select name="file_type">
                    @foreach($fileTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="tw:mb-[10px] tw:flex tw:flex-col">
                <x-ui.label>Chọn file</x-ui.label>
                <x-ui.input type="file" name="file" required />
            </div>

            <div class="tw:flex tw:flex-col">
                <x-ui.label>Ghi chú</x-ui.label>
                <x-ui.input name="note" placeholder="VD: HĐLĐ bản scan, CCCD mặt trước..." />
            </div>
        </div>

        <div class="tw:sticky tw:bottom-0 tw:z-[2] tw:flex tw:justify-end tw:gap-2 tw:border-t tw:border-solid tw:border-[#edf2f7] tw:bg-[#f8fafc] tw:px-[18px] tw:py-[14px]">
            <x-ui.button onclick="hideEmpModal('fileModal')">Huỷ</x-ui.button>
            <x-ui.button variant="primary" type="submit">+ Upload</x-ui.button>
        </div>
    </form>
</x-hr.employee-modal>

<script>
    function openEmpModal(id){
        document.getElementById(id).classList.add('show');
    }

    function hideEmpModal(id){
        document.getElementById(id).classList.remove('show');
    }

    function closeEmpModal(event, id){
        if(event.target.id === id){
            hideEmpModal(id);
        }
    }

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            document.querySelectorAll('[data-ego-emp-modal]').forEach(function(modal){
                modal.classList.remove('show');
            });
        }
    });
</script>
@endsection
