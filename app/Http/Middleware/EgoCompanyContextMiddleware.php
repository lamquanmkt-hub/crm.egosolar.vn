<?php

namespace App\Http\Middleware;

use App\Support\EgoDefaultCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;
use Throwable;

class EgoCompanyContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        foreach (['login', 'logout', 'register'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        try {
            $company = EgoDefaultCompany::forceSession($request);
            $companyId = (int) $company->id;
            $companyName = trim((string) ($company->name ?? 'Công ty TNHH EGO Việt Nam'));

            // Ghi đè cả input để controller không thể nhận nhầm công ty QT.
            $request->merge([
                'company_id' => $companyId,
                'company' => $companyName,
            ]);

            View::share('activeCompanyId', $companyId);
            View::share('activeCompanyName', $companyName);

            Cookie::queue(cookie(
                'ego_last_company_id',
                (string) $companyId,
                60 * 24 * 365,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
        } catch (Throwable $exception) {
            report($exception);

            abort(500, 'Không tìm thấy công ty EGO Việt Nam để thiết lập làm mặc định.');
        }

        return $next($request);
    }
}
