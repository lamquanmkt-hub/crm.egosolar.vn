{{--
    EGO_VIEW_CHET — VIEW CHẾT, KHÔNG AI RENDER (rà soát 2026-09-06)

    Route `hr.online-work.create` (routes/hr.php) là closure chỉ redirect sang
    `hr.leave.create?request_type=wfh`; không controller/@include nào gọi view này.

    CHƯA XOÁ theo yêu cầu: chỉ đánh dấu để lần sau khỏi rà lại.
    Nếu bạn đấu view này vào một route/@include, hãy XOÁ dấu này —
    tests/Feature/View/DeadViewsMarkedTest.php sẽ báo đỏ để nhắc.
--}}
@extends('layouts.app')

@section('content')
<style> .online-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
    }.online-shell{
        padding:22px;
    }.online-hero{
        border-radius:24px;
        padding:24px;
        color:#fff;
        background:
            radial-gradient(700px 280px at 88% 0%, rgba(34,211,238,.24), transparent 60%),
            linear-gradient(135deg,#020617,#075985 58%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
    }.online-hero h3{
        font-size:25px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }.online-hero p{
        margin:5px 0 0;
        opacity:.84;
        font-size:13px;
    }.online-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
        overflow:hidden;
    }.online-card-head{
        padding:15px 18px;
        border-bottom:1px solid #e5eaf1;
        font-weight:950;
        color:#0f172a;
    }.online-card-body{
        padding:18px;
    }.ow-label{
        font-size:12px;
        font-weight:850;
        color:#334155;
    }.ow-input{
        border-radius:13px;
        border-color:#dbe3ee;
        font-size:13px;
    }.btn-pill{
        border-radius:999px;
        font-size:13px;
        font-weight:850;
        padding:8px 16px;
    }.online-layout{
        display:grid;
        grid-template-columns:1fr 340px;
        gap:16px;
        align-items:start;
    }.tip-box{
        padding:14px;
        border-bottom:1px solid #e5eaf1;
    }.tip-box:last-child{
        border-bottom:0;
    }.tip-title{
        font-weight:950;
        color:#0f172a;
    }.tip-desc{
        color:#64748b;
        font-size:12px;
        margin-top:4px;
        line-height:1.5;
    }@media(max-width:1100px){.online-layout{
            grid-template-columns:1fr;
        }
    }@media(max-width:768px){.online-shell{
            padding:14px;
        }.online-hero{
            flex-direction:column;
            align-items:flex-start;
        }
    }
</style>

<div class="online-page">
    <div class="online-shell">

        <div class="online-hero">
            <div>
                <h3>Đơn xin làm online</h3>
                <p>Gửi đề xuất làm việc online để trưởng bộ phận hoặc sếp duyệt.</p>
            </div>

            <a href="{{ route('hr.attendance.my') }}" class="btn btn-light btn-pill">
                <i class="bi bi-arrow-left"></i> Quay lại chấm công
            </a>
        </div>

        @if(session('success'))
            <x-ui.alert variant="success" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
                {{ session('success') }}
            </x-ui.alert>
        @endif

        @if($errors->any())
            <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
                <b>Chưa gửi được đơn.</b>
                <ul class="tw:mb-0 tw:mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="online-layout">
            <div class="online-card">
                <div class="online-card-head">
                    <i class="bi bi-laptop"></i> Thông tin đơn làm online
                </div>

                <div class="online-card-body">
                    <form method="POST" action="#">
                        @csrf

                        <div class="tw:row tw:g-3">
                            <div class="tw:md:col12-6 tw:mb-4">
                                <x-ui.label class="ow-label">Ngày bắt đầu</x-ui.label>
                                <x-ui.input type="date" name="start_date" class="ow-input" required />
                            </div>

                            <div class="tw:md:col12-6 tw:mb-4">
                                <x-ui.label class="ow-label">Ngày kết thúc</x-ui.label>
                                <x-ui.input type="date" name="end_date" class="ow-input" required />
                            </div>
                        </div>

                        <div class="tw:mb-4">
                            <x-ui.label class="ow-label">Hình thức</x-ui.label>
                            <x-ui.select name="online_type" class="ow-input">
                                <option value="full_day">Làm online cả ngày</option>
                                <option value="morning">Làm online buổi sáng</option>
                                <option value="afternoon">Làm online buổi chiều</option>
                                <option value="custom">Khung giờ tùy chỉnh</option>
                            </x-ui.select>
                        </div>

                        <div class="tw:mb-4">
                            <x-ui.label class="ow-label">Lý do xin làm online</x-ui.label>
                            <x-ui.input as="textarea" name="reason"
                                      rows="5"
                                      class="ow-input"
                                      placeholder="Ví dụ: cần xử lý công việc từ xa, đi công tác, việc cá nhân nhưng vẫn đảm bảo tiến độ..."
                                      required></x-ui.input>
                        </div>

                        <div class="tw:mb-4">
                            <x-ui.label class="ow-label">Kế hoạch công việc trong thời gian online</x-ui.label>
                            <x-ui.input as="textarea" name="work_plan"
                                      rows="5"
                                      class="ow-input"
                                      placeholder="Liệt kê các đầu việc sẽ thực hiện, deadline, cách báo cáo kết quả..."></x-ui.input>
                        </div>

                        <x-ui.alert variant="warning" class="tw:rounded-[1rem] tw:text-[0.875em]">
                            Trang này hiện là giao diện sẵn. Nếu hệ thống của bạn đã có controller lưu đơn làm online,
                            chỉ cần đổi `action="#"` thành route lưu đơn thật.
                        </x-ui.alert>

                        <div class="tw:flex tw:justify-end tw:gap-2 flex-wrap">
                            <a href="{{ route('hr.attendance.my') }}" class="btn btn-outline-secondary btn-pill">
                                Hủy
                            </a>

                            <button type="submit" class="btn btn-success btn-pill px-5">
                                <i class="bi bi-send"></i> Gửi đơn
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="online-card">
                <div class="online-card-head">
                    <i class="bi bi-info-circle"></i> Lưu ý
                </div>

                <div class="tip-box">
                    <div class="tip-title">Ghi rõ lý do</div>
                    <div class="tip-desc">Sếp sẽ dễ duyệt hơn nếu bạn trình bày rõ vì sao cần làm online.</div>
                </div>

                <div class="tip-box">
                    <div class="tip-title">Có kế hoạch công việc</div>
                    <div class="tip-desc">Nên ghi rõ các đầu việc sẽ hoàn thành trong thời gian làm online.</div>
                </div>

                <div class="tip-box">
                    <div class="tip-title">Vẫn cần báo cáo kết quả</div>
                    <div class="tip-desc">Sau khi được duyệt, bạn nên cập nhật tiến độ hoặc báo cáo qua hệ thống công việc.</div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection