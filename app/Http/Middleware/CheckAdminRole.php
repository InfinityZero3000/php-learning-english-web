<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Lấy thông tin user đăng nhập từ Request
        $user = $request->user();

        // Kiểm tra user có tồn tại và role_id = 1 (Admin) hay không
        if ($user && $user->role_id == 1) {
            return $next($request);
        }

        abort(403, 'Bạn không có quyền truy cập vào khu vực quản trị này.');
    }
}