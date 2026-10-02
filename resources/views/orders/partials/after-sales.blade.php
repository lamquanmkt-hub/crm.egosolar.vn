{{--
    EGO_VIEW_CHET — VIEW CHẾT, KHÔNG AI RENDER (rà soát 2026-09-25)

    Partial này chỉ được @include từ orders/show-legacy — mà view đó CŨNG đã đánh dấu chết.
    Không route/controller nào render nó, không view sống nào gọi tới.

    Vì thế khối PHP nội tuyến gọi thẳng Eloquent (OrderReturn::where(...)) trong đây KHÔNG
    được dọn: theo rules/blade-views.md thì không sửa view chết, chỉ đánh dấu và đề xuất xoá.
    (Chú thích này cố ý KHÔNG viết chữ @ + php — Blade tách directive đó trước khi bỏ chú
    thích nên sẽ mở một khối PHP và nuốt tới @ + endphp thật phía dưới. Đã vấp 2026-09-25.)
    Lỗ của guard cũ (chỉ bắt DB::*) đã vá ở ViewsDoNotQueryDatabaseTest cùng đợt này.

    CHƯA XOÁ theo yêu cầu: chỉ đánh dấu để lần sau khỏi rà lại.
    Nếu bạn đấu view này vào một route/@include sống, hãy XOÁ dấu này —
    tests/Feature/View/DeadViewsMarkedTest.php sẽ báo đỏ để nhắc.
--}}
<link rel="stylesheet" href="{{ asset('css/order-after-sales.css') }}">
@php
$oasReturns=\App\Models\CRM\Orders\OrderReturn::where('order_id',$order->id)->latest('id')->get();
$oasActive=$oasReturns->whereNotIn('status',['completed','rejected','cancelled'])->count();
@endphp
<div class="oas-after-sales-box">
    <div class="head"><div><strong>Đổi trả & hoàn tiền</strong><div class="oas-muted">Hủy trước xuất kho, thu hồi sau xuất kho, hoàn một phần, xử lý kho và hoàn tiền.</div></div><div class="oas-actions"><a class="oas-btn" href="{{ route('orders.returns.index',$order) }}">Xem hồ sơ</a><a class="oas-btn oas-btn-primary" href="{{ route('orders.returns.create',$order) }}">+ Tạo yêu cầu</a></div></div>
    <div class="body"><div class="oas-mini-grid"><div class="oas-mini"><strong>{{ $oasReturns->count() }}</strong><span>Tổng yêu cầu</span></div><div class="oas-mini"><strong>{{ $oasActive }}</strong><span>Đang xử lý</span></div><div class="oas-mini"><strong>{{ $order->inventory_issued?'Đã trừ tồn':'Chưa xuất' }}</strong><span>Trạng thái kho đơn gốc</span></div></div>@if($oasReturns->count())<div style="margin-top:12px">@foreach($oasReturns->take(3) as $r)<a href="{{ route('order-returns.show',$r) }}" class="oas-badge {{ $r->status==='completed'?'oas-badge-green':'oas-badge-blue' }}" style="margin:3px">{{ $r->return_code }} · {{ $r->status_label }}</a>@endforeach</div>@endif</div>
</div>
