@extends('layouts.app')

@section('content')
<style>
.pss-page{max-width:1500px;margin:0 auto;padding:18px 18px 40px;color:#102a46}.pss-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:14px}.pss-breadcrumb{font-size:12px;font-weight:800;color:#75879a;margin-bottom:7px}.pss-title{font-size:27px;font-weight:900;margin:0}.pss-sub{font-size:13px;color:#708299;margin-top:6px}.pss-actions{display:flex;gap:8px;flex-wrap:wrap}.pss-btn{height:38px;border-radius:10px;padding:0 14px;border:1px solid #d7e2ec;background:#fff;color:#123452;font-weight:800;font-size:13px;display:inline-flex;align-items:center;gap:7px;text-decoration:none;cursor:pointer}.pss-btn-primary{background:#0b3d68;border-color:#0b3d68;color:#fff}.pss-btn-danger{background:#fff7f7;border-color:#efc8cb;color:#bd3d47}.pss-card{background:#fff;border:1px solid #e0e9f0;border-radius:17px;margin-bottom:14px;overflow:hidden}.pss-card-head{padding:15px 17px;border-bottom:1px solid #edf2f6;display:flex;justify-content:space-between;align-items:center}.pss-card-head h3{font-size:14px;font-weight:900;margin:0}.pss-body{padding:16px 17px}.pss-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.pss-field label{display:block;font-size:11px;font-weight:900;color:#62778d;text-transform:uppercase;margin-bottom:6px}.pss-input,.pss-select,.pss-textarea{width:100%;border:1px solid #d5e0e9;border-radius:10px;color:#123452;background:#fff}.pss-input,.pss-select{height:39px;padding:0 10px}.pss-textarea{min-height:80px;padding:10px}.pss-checks{display:flex;gap:18px;flex-wrap:wrap;margin-top:12px}.pss-check{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:800;color:#50677d}.pss-component{padding:14px 16px;border-bottom:1px solid #edf2f6}.pss-component:last-child{border-bottom:0}.pss-component-top{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:11px}.pss-component-name{font-weight:900}.pss-code{font-size:11px;color:#8797a8;margin-top:2px}.pss-row-actions{display:flex;gap:7px}.pss-badge{font-size:10px;font-weight:900;padding:5px 8px;border-radius:999px;background:#edf5fb;color:#2d5677}.pss-help{font-size:12px;color:#76889a;line-height:1.6}.pss-add{background:#f8fbfd}.pss-status{padding:10px 14px;border-radius:12px;margin-bottom:12px}.pss-status.ok{background:#eaf8f1;color:#0d7658}.pss-status.err{background:#fff0f1;color:#b53d46}@media(max-width:950px){.pss-head{flex-direction:column}.pss-grid{grid-template-columns:1fr 1fr}}@media(max-width:650px){.pss-grid{grid-template-columns:1fr}}
</style>

<div class="pss-page">
    <div class="pss-head">
        <div>
            <div class="pss-breadcrumb">Tài chính kế toán &nbsp;›&nbsp; Bảng lương &nbsp;›&nbsp; Cấu hình phiếu lương</div>
            <h1 class="pss-title">Cấu hình phiếu lương</h1>
            <div class="pss-sub">Quản trị viên có thể đổi tên, thêm, sửa, xóa, ẩn/hiện và sắp xếp toàn bộ thành phần trên phiếu lương.</div>
        </div>
        <div class="pss-actions">
            <a class="pss-btn" href="{{ route('finance.salary') }}"><i class="bi bi-arrow-left"></i>Quay lại bảng lương</a>
        </div>
    </div>

    @if(session('success'))<div class="pss-status ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="pss-status err">{{ session('error') }}</div>@endif

    <section class="pss-card">
        <div class="pss-card-head"><h3>Cấu hình chung</h3><span class="pss-badge">ADMIN ONLY</span></div>
        <div class="pss-body">
            <form method="POST" action="{{ route('finance.salary.settings.general') }}">
                @csrf
                <div class="pss-grid">
                    <div class="pss-field"><label>Tiêu đề phiếu lương</label><input class="pss-input" name="title" value="{{ old('title',$settings['title'] ?? '') }}" required></div>
                    <div class="pss-field" style="grid-column:span 2"><label>Mô tả ngắn</label><input class="pss-input" name="subtitle" value="{{ old('subtitle',$settings['subtitle'] ?? '') }}"></div>
                    <div class="pss-field" style="grid-column:1/-1"><label>Ghi chú cuối phiếu</label><textarea class="pss-textarea" name="footer_note">{{ old('footer_note',$settings['footer_note'] ?? '') }}</textarea></div>
                </div>
                <div class="pss-checks">
                    <label class="pss-check"><input type="checkbox" name="show_attendance" value="1" @checked(($settings['show_attendance'] ?? '1') === '1')> Hiển thị chấm công</label>
                    <label class="pss-check"><input type="checkbox" name="show_kpi_summary" value="1" @checked(($settings['show_kpi_summary'] ?? '1') === '1')> Hiển thị KPI</label>
                    <label class="pss-check"><input type="checkbox" name="show_note" value="1" @checked(($settings['show_note'] ?? '1') === '1')> Hiển thị ghi chú</label>
                </div>
                <div style="margin-top:14px"><button class="pss-btn pss-btn-primary" type="submit"><i class="bi bi-check2-circle"></i>Lưu cấu hình chung</button></div>
            </form>
        </div>
    </section>

    <section class="pss-card">
        <div class="pss-card-head"><h3>Thành phần trên phiếu lương</h3><span class="pss-badge">{{ $components->count() }} mục</span></div>
        @forelse($components as $component)
            <div class="pss-component">
                <div class="pss-component-top">
                    <div><div class="pss-component-name">{{ $component->label }}</div><div class="pss-code">{{ $component->code }} · {{ $sourceOptions[$component->source] ?? $component->source }}</div></div>
                    <div class="pss-row-actions">
                        <form method="POST" action="{{ route('finance.salary.settings.components.destroy',$component->id) }}" onsubmit="return confirm('Xóa mục này khỏi mẫu phiếu lương? Dữ liệu lịch sử vẫn được giữ.');">
                            @csrf @method('DELETE')
                            <button class="pss-btn pss-btn-danger" type="submit"><i class="bi bi-trash"></i>Xóa</button>
                        </form>
                    </div>
                </div>
                <form method="POST" action="{{ route('finance.salary.settings.components.update',$component->id) }}">
                    @csrf @method('PUT')
                    <div class="pss-grid">
                        <div class="pss-field"><label>Tên hiển thị</label><input class="pss-input" name="label" value="{{ $component->label }}" required></div>
                        <div class="pss-field"><label>Nhóm</label><select class="pss-select" name="section"><option value="info" @selected($component->section==='info')>Thông tin</option><option value="income" @selected($component->section==='income')>Thu nhập (+)</option><option value="deduction" @selected($component->section==='deduction')>Khấu trừ (-)</option></select></div>
                        <div class="pss-field"><label>Nguồn dữ liệu</label><select class="pss-select" name="source">@foreach($sourceOptions as $key=>$label)<option value="{{ $key }}" @selected($component->source===$key)>{{ $label }}</option>@endforeach</select></div>
                        <div class="pss-field"><label>Định dạng</label><select class="pss-select" name="display_format"><option value="money" @selected($component->display_format==='money')>Tiền VNĐ</option><option value="number" @selected($component->display_format==='number')>Số</option><option value="percent" @selected($component->display_format==='percent')>Phần trăm</option></select></div>
                        <div class="pss-field"><label>Giá trị mặc định</label><input class="pss-input" type="number" min="0" step="1" name="default_amount" value="{{ (float)$component->default_amount }}"></div>
                        <div class="pss-field"><label>Thứ tự</label><input class="pss-input" type="number" min="0" name="sort_order" value="{{ (int)$component->sort_order }}"></div>
                        <div class="pss-field" style="grid-column:1/-1"><label>Ghi chú cấu hình</label><input class="pss-input" name="note" value="{{ $component->note }}"></div>
                    </div>
                    <div class="pss-checks">
                        <label class="pss-check"><input type="checkbox" name="affects_total" value="1" @checked($component->affects_total)> Tính vào thực nhận</label>
                        <label class="pss-check"><input type="checkbox" name="editable_amount" value="1" @checked($component->editable_amount)> Cho phép sửa trên phiếu</label>
                        <label class="pss-check"><input type="checkbox" name="show_on_payslip" value="1" @checked($component->show_on_payslip)> Hiện trên phiếu</label>
                        <label class="pss-check"><input type="checkbox" name="is_active" value="1" @checked($component->is_active)> Đang sử dụng</label>
                    </div>
                    <div style="margin-top:12px"><button class="pss-btn pss-btn-primary" type="submit"><i class="bi bi-save"></i>Lưu mục này</button></div>
                </form>
            </div>
        @empty
            <div class="pss-body">Chưa có thành phần phiếu lương. Hãy thêm mục mới bên dưới.</div>
        @endforelse
    </section>

    <section class="pss-card pss-add">
        <div class="pss-card-head"><h3>Thêm thành phần mới</h3><span class="pss-badge">+ ADD</span></div>
        <div class="pss-body">
            <form method="POST" action="{{ route('finance.salary.settings.components.store') }}">
                @csrf
                <div class="pss-grid">
                    <div class="pss-field"><label>Tên hiển thị</label><input class="pss-input" name="label" placeholder="Ví dụ: Thưởng chuyên cần" required></div>
                    <div class="pss-field"><label>Mã nội bộ (không bắt buộc)</label><input class="pss-input" name="code" placeholder="tu_dong_tao_neu_bo_trong"></div>
                    <div class="pss-field"><label>Nhóm</label><select class="pss-select" name="section"><option value="income">Thu nhập (+)</option><option value="deduction">Khấu trừ (-)</option><option value="info">Thông tin</option></select></div>
                    <div class="pss-field"><label>Nguồn dữ liệu</label><select class="pss-select" name="source">@foreach($sourceOptions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="pss-field"><label>Định dạng</label><select class="pss-select" name="display_format"><option value="money">Tiền VNĐ</option><option value="number">Số</option><option value="percent">Phần trăm</option></select></div>
                    <div class="pss-field"><label>Giá trị mặc định</label><input class="pss-input" type="number" min="0" name="default_amount" value="0"></div>
                    <div class="pss-field"><label>Thứ tự</label><input class="pss-input" type="number" min="0" name="sort_order" value="500"></div>
                    <div class="pss-field" style="grid-column:span 2"><label>Ghi chú</label><input class="pss-input" name="note" placeholder="Mô tả cách dùng mục này"></div>
                </div>
                <div class="pss-checks">
                    <label class="pss-check"><input type="checkbox" name="affects_total" value="1" checked> Tính vào thực nhận</label>
                    <label class="pss-check"><input type="checkbox" name="editable_amount" value="1" checked> Cho phép sửa trên phiếu</label>
                    <label class="pss-check"><input type="checkbox" name="show_on_payslip" value="1" checked> Hiện trên phiếu</label>
                    <label class="pss-check"><input type="checkbox" name="is_active" value="1" checked> Đang sử dụng</label>
                </div>
                <div style="margin-top:14px"><button class="pss-btn pss-btn-primary" type="submit"><i class="bi bi-plus-circle"></i>Thêm thành phần</button></div>
            </form>
            <div class="pss-help" style="margin-top:12px">Mục nguồn <b>Nhập tay theo từng phiếu</b> phù hợp cho khoản phát sinh riêng. Mục nguồn hệ thống sẽ tự lấy dữ liệu hiện có. Xóa một mục chỉ xóa khỏi mẫu hiển thị, không xóa dữ liệu lương lịch sử.</div>
        </div>
    </section>
</div>
@endsection
