<?php

namespace App\Http\Middleware;

use App\Contracts\Services\PageAccessServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePageAccess
{
    public function __construct(private readonly PageAccessServiceInterface $pageAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        /*
         * EGO_WAREHOUSE_SUPPLIER_DEBT_FULL_ACCESS
         *
         * Kho được thao tác toàn bộ module Công nợ nhà cung cấp.
         * Không cấp page.finance để tránh mở toàn bộ Tài chính.
         */
        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['warehouse', 'kho'])
        ) {
            /*
             * Toàn bộ nghiệp vụ Supplier Debts:
             * GET / POST / PUT / PATCH / DELETE.
             */
            if (
                $request->is('finance/supplier-debts')
                || $request->is('finance/supplier-debts/*')
            ) {
                return $next($request);
            }

            /*
             * Sau khi tạo ĐNTT từ công nợ NCC, controller redirect sang
             * payment-requests/{id}. Cho Kho xem ĐÚNG phiếu liên kết
             * với công nợ NCC, không mở tất cả ĐNTT.
             */
            if (
                in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)
                && preg_match(
                    '#^payment-requests/([0-9]+)$#',
                    trim($request->path(), '/'),
                    $supplierDebtPaymentMatch
                )
            ) {
                try {
                    $paymentRequestId = (int) $supplierDebtPaymentMatch[1];

                    if (
                        \Illuminate\Support\Facades\Schema::hasTable(
                            'finance_supplier_debt_payments'
                        )
                        && \Illuminate\Support\Facades\DB::table(
                            'finance_supplier_debt_payments'
                        )
                            ->where(
                                'payment_request_id',
                                $paymentRequestId
                            )
                            ->exists()
                    ) {
                        return $next($request);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        /*
         * EGO_HR_SELF_SERVICE_ACCESS_V1
         *
         * Chấm công cá nhân / nghỉ phép / tăng ca là chức năng cá nhân dùng
         * xuyên mọi workspace. Không để PageAccessService chặn theo phòng ban.
         * Các thao tác nhạy cảm (duyệt/từ chối/chuyển người duyệt, tải/xoá file)
         * vẫn được controller kiểm tra quyền theo từng bản ghi.
         */
        $method = strtoupper($request->method());
        $path = trim($request->path(), '/');

        $isPersonalAttendance =
            ($method === 'GET' && in_array($path, [
                'nhan-su/cham-cong-cua-toi',
                'nhan-su/huong-dan-cham-cong/dien-thoai',
                'nhan-su/huong-dan-cham-cong/may-tinh',
            ], true))
            || ($method === 'POST' && in_array($path, [
                'nhan-su/cham-cong/check-in',
                'nhan-su/cham-cong/check-out',
            ], true));

        $isLeaveSelfService =
            $path === 'nhan-su/leave-requests'
            || str_starts_with($path, 'nhan-su/leave-requests/')
            || $path === 'nhan-su/online-work/create';

        $isOvertimeSelfService =
            $path === 'nhan-su/tang-ca'
            || str_starts_with($path, 'nhan-su/tang-ca/');

        if ($isPersonalAttendance || $isLeaveSelfService || $isOvertimeSelfService) {
            return $next($request);
        }

        $permission = $this->pageAccess->permissionForRequest($request);

        if ($permission === null || $this->pageAccess->canAccess($user, $permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Bạn không được cấp quyền truy cập chức năng này.',
                'required_permission' => $permission,
            ], 403);
        }

        abort(403, 'Bạn không được cấp quyền truy cập trang này. Vui lòng liên hệ quản trị viên.');
    }
}
