<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Hiển thị form login
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request)
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {

            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'login' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        /*
        | Tài khoản đã vô hiệu hoá KHÔNG được vào hệ thống.
        |
        | Trước đây đoạn kiểm tra này bị comment, nên 20 tài khoản is_active=0
        | trên production vẫn đăng nhập được — và vì chúng cũng không được gán
        | role nào, EnforcePageAccess coi như "chưa bật kiểm soát quyền trang"
        | và mở gần hết hệ thống cho họ (kể cả trang Cài đặt).
        |
        | Kiểm tra sau attempt (không nhét vào credentials) để phân biệt được
        | "sai mật khẩu" với "tài khoản bị khoá" trong thông báo.
        */
        if ($this->isDeactivated(Auth::user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'login' => 'Tài khoản đã bị vô hiệu hoá. Vui lòng liên hệ quản trị viên.',
            ]);
        }

        $request->session()->regenerate();

        return redirect('/workspace');
    }

    /**
     * Tài khoản có bị vô hiệu hoá không.
     *
     * ⚠️ MẶC ĐỊNH TẮT (`enforce_active_account_on_login` = false).
     *
     * Lý do: dữ liệu `is_active` trên production KHÔNG đáng tin. Rà ngày
     * 2026-08-05 thấy 4 tài khoản có role nhưng `is_active = 0`, trong đó tài
     * khoản "Admin" (role=admin) vẫn đang dùng hệ thống (last_seen cùng ngày).
     * Bật cờ này ngay lập tức sẽ KHOÁ một quản trị viên đang làm việc.
     *
     * Quy trình bật đúng: dọn dữ liệu `is_active` cho các tài khoản còn dùng
     * (xem `permissions:audit-users`) -> xác nhận danh sách với nghiệp vụ ->
     * đặt EGO_ENFORCE_ACTIVE_LOGIN=true.
     *
     * Cột `is_active` chỉ có trên một số phiên bản schema nên kiểm tra trước;
     * thiếu cột thì coi như mọi tài khoản đều hoạt động.
     */
    private function isDeactivated(?\App\Models\User $user): bool
    {
        if (! config('role_permissions.enforce_active_account_on_login', false)) {
            return false;
        }

        if ($user === null || ! \App\Support\SchemaCache::hasColumn($user->getTable(), 'is_active')) {
            return false;
        }

        return ! (bool) $user->is_active;
    }

    /**
     * Đăng xuất
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Chống brute-force
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau 1 phút.',
        ]);
    }

    /**
     * Key throttle theo email + IP
     */
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }
}
