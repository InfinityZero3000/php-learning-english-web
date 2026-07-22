<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
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
     * Cập nhật hồ sơ người dùng.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('new_password')) {
            $user->password = Hash::make($request->new_password);
        }

        $user->save();

        return redirect()
            ->route('profile')
            ->with('success', 'Cập nhật hồ sơ thành công.');
    }
}
