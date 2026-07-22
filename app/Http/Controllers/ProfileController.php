<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Hiển thị hồ sơ người dùng.
     */
    public function index()
    {
        return view('profile.index', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Cập nhật hồ sơ người dùng (tên và/hoặc mật khẩu). Email chỉ đọc.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        $user->name = $request->name;

        if ($request->filled('new_password')) {
            $user->password = Hash::make($request->new_password);
        }

        $user->save();

        return redirect()
            ->route('profile')
            ->with('success', 'Cập nhật hồ sơ thành công.');
    }

    /**
     * Xóa tài khoản người dùng.
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Tài khoản của bạn đã được xóa.');
    }
}
