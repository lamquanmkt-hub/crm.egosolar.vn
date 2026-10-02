{{-- Card một nhân sự trên trang danh sách — dùng chung cho ba khu (ban giám đốc, phòng ban,
     ngừng hợp tác). $card là App\DTOs\Hr\EmployeeCard: mọi chữ đã tính sẵn, ở đây chỉ in. --}}
<div @class(['hr-person-card', 'is-leader' => $card->isLeader])>
    <div class="hr-person-top">
        @if($card->avatarUrl)
            <img src="{{ $card->avatarUrl }}" alt="{{ $card->name }}" class="hr-avatar" onerror="this.remove(); this.parentNode.querySelector('.hr-avatar-fallback').style.display='flex';">
            <div class="hr-avatar-fallback" style="display:none;">{{ $card->initials }}</div>
        @else
            <div class="hr-avatar-fallback">{{ $card->initials }}</div>
        @endif
        <div>
            <div class="hr-person-name">{{ $card->name }}</div>
        </div>
    </div>
    <div class="hr-person-body">
        <div class="hr-meta-highlight">
            <div class="hr-meta-pill hr-meta-pill-department">
                <i class="bi bi-building"></i>
                <span class="label">Phòng ban:</span>
                <span class="value">{{ $card->departmentText }}</span>
            </div>
            <div class="hr-meta-pill hr-meta-pill-position">
                <i class="bi bi-briefcase"></i>
                <span class="label">Chức vụ:</span>
                <span class="value">{{ $card->positionText }}</span>
            </div>
        </div>
        <div class="hr-info-line">
            <i class="bi bi-envelope"></i>
            <span><strong>Email:</strong> {{ $card->email }}</span>
        </div>
        <div class="hr-info-line">
            <i class="bi bi-telephone"></i>
            <span><strong>SĐT:</strong> {{ $card->phoneText }}</span>
        </div>
        @if($card->active)
            <span class="hr-badge hr-badge-green">
                <i class="bi bi-check-circle"></i> Đang hoạt động
            </span>
        @else
            <span class="hr-badge hr-badge-red">
                <i class="bi bi-pause-circle"></i> Đã ngừng hợp tác
            </span>
        @endif
        {{-- EGO_HR_EMPLOYEE_SALARY_CARD_V2_START --}}
        <div class="hr-salary-current">
            <div class="hr-salary-current-label">
                <i class="bi bi-cash-coin"></i>
                <span>{{ $card->salaryLabel }}</span>
            </div>
            <div class="hr-salary-current-value">
                {{ $card->salaryText }}
            </div>
        </div>
        {{-- EGO_HR_EMPLOYEE_SALARY_CARD_V2_END --}}
    </div>
    <div class="hr-actions">
        <x-ui.button href="{{ route('hr.employees.show', $card->id) }}" variant="light" class="hr-btn border">
            <i class="bi bi-eye me-1"></i> Xem
        </x-ui.button>
        <x-ui.button href="{{ route('hr.employees.edit', $card->id) }}" variant="primary" class="hr-btn hr-btn-primary">
            <i class="bi bi-pencil-square me-1"></i> Sửa
        </x-ui.button>
        <form class="employee-delete-form" method="POST" action="{{ route('hr.employees.destroy', $card->id) }}" onsubmit="return confirm('Xóa nhân viên khỏi danh sách và khóa tài khoản? Lịch sử liên quan vẫn được giữ để không lỗi dữ liệu.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-delete-employee">
                <i class="bi bi-trash"></i> Xóa
            </button>
        </form>
    </div>
</div>
