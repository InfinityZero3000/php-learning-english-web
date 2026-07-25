<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $roleId = Role::query()->where('slug', 'learner')->value('id');

        if (! $roleId) {
            throw ValidationException::withMessages(['role' => 'Hệ thống chưa được cấu hình vai trò người học.']);
        }

        $user = User::create([
            ...$request->safe()->only(['name', 'email']),
            'password' => Hash::make($request->string('password')),
            'role_id' => $roleId,
        ]);
        $user->sendEmailVerificationNotification();
        $request->session()->put('verify_email', $user->email);

        return ApiResponse::success(['message' => 'Vui lòng kiểm tra email để xác minh tài khoản.'], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        $user = Auth::user();
        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            $request->session()->put('verify_email', $user->email);

            return response()->json([
                'message' => 'Vui lòng xác minh email trước khi đăng nhập.',
                'code' => 'EMAIL_UNVERIFIED',
            ], 403);
        }

        $request->session()->regenerate();

        return ApiResponse::success(new UserResource($user->load('role')));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()->load('role')));
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
