<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\Admin\UserController;

/*
|--------------------------------------------------------------------------
| Trang chủ
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('home');
});

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::view('/admin', 'admin');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

// Đăng ký
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');

// Đăng nhập
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');

// Quên mật khẩu
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])
    ->name('password.request');

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
    ->name('password.email');

// Đăng xuất
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Profile (Yêu cầu đăng nhập)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Hồ sơ người học
    Route::get('/profile', [ProfileController::class, 'index'])
        ->name('profile');

    // Cập nhật hồ sơ
    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Admin (Yêu cầu đăng nhập & Quyền Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    // Trang Dashboard Admin
    Route::get('/dashboard', function () {
        return view('admin');
    })->name('dashboard');
});

// Nhóm Route Admin yêu cầu Đăng nhập + Middleware CheckAdminRole
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [UserController::class, 'index'])->name('admin.dashboard');
    Route::patch('/users/{id}/role', [UserController::class, 'updateRole'])->name('admin.users.updateRole');
});