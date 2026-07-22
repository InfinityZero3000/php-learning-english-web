<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{
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
