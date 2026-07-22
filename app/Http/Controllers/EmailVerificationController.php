<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Hiển thị trang "Kiểm tra hộp thư" sau khi đăng ký.
     */
    public function notice(Request $request)
    {
        $email = $request->session()->get('verify_email');

        if (! $email) {
            return redirect()->route('register');
        }

        return view('auth.verify-notice', ['email' => $email]);
    }

    /**
     * Gửi lại email xác nhận.
     */
    public function resend(Request $request)
    {
        $email = $request->session()->get('verify_email');
        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user) {
            return redirect()->route('register');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('verification.notice')
            ->with('success', 'Đã gửi lại email xác minh.');
    }

    /**
     * Xác nhận email từ link gửi qua thư điện tử.
     */
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('login')
            ->with('success', 'Email đã được xác nhận. Vui lòng đăng nhập.');
    }
}
