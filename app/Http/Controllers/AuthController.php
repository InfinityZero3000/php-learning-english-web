<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Hiển thị trang đăng ký
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Xử lý đăng ký
     */
    public function register(RegisterRequest $request)
    {
        $roleId = Role::query()->where('slug', 'learner')->value('id');

        if (! $roleId) {
            throw ValidationException::withMessages([
                'role' => 'Hệ thống chưa được cấu hình vai trò người học.',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $roleId,
        ]);

        $user->sendEmailVerificationNotification();

        $request->session()->put('verify_email', $user->email);

        return redirect()->route('verification.notice');
    }

    /**
     * Hiển thị trang đăng nhập
     */
    public function showLogin()
    {
        return app()->isProduction()
            ? redirect()->away(config('app.frontend_url').'/login')
            : view('auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if (! $user->hasVerifiedEmail()) {
                Auth::logout();

                $request->session()->put('verify_email', $user->email);

                return redirect()->route('verification.notice')
                    ->with('notice', 'Vui lòng xác nhận email trước khi đăng nhập.');
            }

            $request->session()->regenerate();

            return redirect()->route('profile');
        }

        return back()->withErrors([
            'email' => 'Email hoặc mật khẩu không đúng.',
        ])->withInput();
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
}
