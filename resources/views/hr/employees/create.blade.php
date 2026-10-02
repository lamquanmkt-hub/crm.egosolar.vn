@extends('layouts.app')

@section('content')
{{-- `.container` quy đổi theo GIÁ TRỊ: width 100% + đệm calc(1.5rem*.5)=12px + margin auto,
     max-width 540/720/960/1140/1320px tại 576/768/992/1200/1400px (thang Bootstrap). --}}
<div class="tw:w-full tw:px-3 tw:mx-auto tw:min-[36rem]:max-w-[540px] tw:min-[48rem]:max-w-[720px] tw:min-[62rem]:max-w-[960px] tw:min-[75rem]:max-w-[1140px] tw:min-[87.5rem]:max-w-[1320px] tw:py-4">
    <div class="tw:flex tw:justify-between tw:items-start tw:flex-wrap tw:gap-2 tw:mb-4">
        <div>
            <h2 class="tw:mb-1 tw:font-bold">Thêm nhân viên</h2>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Tạo hồ sơ nhân sự mới</div>
        </div>

        <x-ui.button href="{{ route('hr.employees.index') }}" variant="outline-secondary">
            <i class="bi bi-arrow-left tw:mr-1"></i> Quay lại
        </x-ui.button>
    </div>

    <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)]">
        <x-ui.card-body>
            <form method="POST" action="{{ route('hr.employees.store') }}">
                @csrf

                <div class="tw:row tw:g-3">
                    <div class="tw:md:col12-6">
                        <x-ui.label>Họ và tên</x-ui.label>
                        <x-ui.input type="text" name="name" :invalid="$errors->has('name')"
                               value="{{ old('name') }}" />
                        @error('name') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Email</x-ui.label>
                        <x-ui.input type="email" name="email" :invalid="$errors->has('email')"
                               value="{{ old('email') }}" />
                        @error('email') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Số điện thoại</x-ui.label>
                        <x-ui.input type="text" name="phone_number" :invalid="$errors->has('phone_number')"
                               value="{{ old('phone_number') }}" />
                        @error('phone_number') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Vai trò</x-ui.label>
                        <x-ui.select name="role" :invalid="$errors->has('role')">
                            <option value="">-- Chọn vai trò --</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        @error('role') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Phòng ban</x-ui.label>
                        <x-ui.select name="department_id" :invalid="$errors->has('department_id')">
                            <option value="">-- Chọn phòng ban --</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        @error('department_id') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Chức vụ</x-ui.label>
                        <x-ui.select name="position_id" :invalid="$errors->has('position_id')">
                            <option value="">-- Chọn chức vụ --</option>
                            @foreach($positions as $position)
                                <option value="{{ $position->id }}" {{ old('position_id') == $position->id ? 'selected' : '' }}>
                                    {{ $position->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        @error('position_id') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Mật khẩu</x-ui.label>
                        <x-ui.input type="password" name="password" :invalid="$errors->has('password')" />
                        @error('password') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Xác nhận mật khẩu</x-ui.label>
                        <x-ui.input type="password" name="password_confirmation" />
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Trạng thái</x-ui.label>
                        <x-ui.select name="is_active" :invalid="$errors->has('is_active')">
                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Đang hoạt động</option>
                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Ngưng hoạt động</option>
                        </x-ui.select>
                        @error('is_active') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                    </div>

                    <div class="tw:col12-12">
                        <div class="tw:[border:1px_solid_#dee2e6] tw:rounded-[1rem] tw:p-4 tw:bg-[rgb(248,249,250)]">
                            <div class="tw:font-bold tw:mb-2"><i class="bi bi-cash-coin tw:mr-1"></i> Mức lương</div>
                            <div class="tw:row tw:g-3">
                                <div class="tw:md:col12-4">
                                    <x-ui.label>Lương chính thức</x-ui.label>
                                    <x-ui.input type="text" name="official_salary" :invalid="$errors->has('official_salary')" value="{{ old('official_salary') }}" placeholder="VD: 15000000 hoặc 15.000.000" />
                                    @error('official_salary') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                                </div>

                                <div class="tw:md:col12-4">
                                    <x-ui.label>Lương thử việc</x-ui.label>
                                    <x-ui.input type="text" name="probation_salary" :invalid="$errors->has('probation_salary')" value="{{ old('probation_salary') }}" placeholder="VD: 12000000" />
                                    @error('probation_salary') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                                </div>

                                <div class="tw:md:col12-4">
                                    <x-ui.label>Lương thực tập</x-ui.label>
                                    <x-ui.input type="text" name="internship_salary" :invalid="$errors->has('internship_salary')" value="{{ old('internship_salary') }}" placeholder="VD: 4000000" />
                                    @error('internship_salary') <x-ui.field-error>{{ $message }}</x-ui.field-error> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tw:mt-6 tw:flex tw:gap-2">
                    <x-ui.button variant="primary" type="submit">
                        <i class="bi bi-save tw:mr-1"></i> Lưu nhân viên
                    </x-ui.button>

                    <x-ui.button href="{{ route('hr.employees.index') }}" variant="light" class="tw:[border:1px_solid_#dee2e6]">
                        Huỷ
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection