<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\EgoDefaultCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tự động vào EGO Việt Nam, không còn lựa chọn EGO Quốc Tế.
 */
final class AutoCompanyContextController extends Controller
{
    /** Số ngày giữ cookie ghi nhớ công ty đang chọn. */
    private const COOKIE_LIFETIME_DAYS = 365;

    /**
     * GET /chon-cong-ty — kích hoạt công ty mặc định rồi về trang chủ.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return $this->activateVietnamCompany($request);
    }

    /**
     * POST /chon-cong-ty — cùng hành vi với GET, giữ lại cho form cũ.
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->activateVietnamCompany($request);
    }

    /**
     * Ghi công ty EGO Việt Nam vào session + cookie ghi nhớ.
     *
     * Khi bảng companies chưa có bản ghi khớp (môi trường mới, dữ liệu chưa
     * seed) thì KHÔNG để lỗi vọt lên thành trang 500 — chuyển hướng kèm thông
     * báo để người dùng còn thao tác được phần còn lại của CRM.
     */
    private function activateVietnamCompany(Request $request): RedirectResponse
    {
        try {
            $company = EgoDefaultCompany::forceSession($request);
        } catch (RuntimeException $exception) {
            Log::warning('Không kích hoạt được công ty mặc định', [
                'error' => $exception->getMessage(),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            return redirect('/')->with(
                'error',
                'Chưa cấu hình công ty EGO Việt Nam trong hệ thống. Vui lòng liên hệ quản trị viên.'
            );
        }

        Cookie::queue(cookie(
            'ego_last_company_id',
            (string) (int) $company->id,
            60 * 24 * self::COOKIE_LIFETIME_DAYS,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));

        return redirect('/');
    }
}
