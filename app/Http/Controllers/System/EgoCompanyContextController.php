<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\EgoDefaultCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * CRM này chỉ dùng EGO Việt Nam.
 * Mọi thao tác chọn/đổi công ty đều quay về EGO Việt Nam.
 */
class EgoCompanyContextController extends Controller
{
    public function select(Request $request)
    {
        return $this->activateVietnamCompany($request);
    }

    public function store(Request $request)
    {
        return $this->activateVietnamCompany($request);
    }

    public function reset(Request $request)
    {
        return $this->activateVietnamCompany($request);
    }

    private function activateVietnamCompany(Request $request)
    {
        $company = EgoDefaultCompany::forceSession($request);
        $companyId = (int) $company->id;

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

        return redirect('/');
    }
}
