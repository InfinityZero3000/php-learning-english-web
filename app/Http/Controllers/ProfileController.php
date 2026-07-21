<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Auth;

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
     * Cập nhật hồ sơ người dùng.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return redirect()
            ->route('profile')
            ->with('success', 'Cập nhật hồ sơ thành công.');
    }
}