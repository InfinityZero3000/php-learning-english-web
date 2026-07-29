<?php

namespace App\Support;

class SafeFrontendPath
{
    public static function normalize(?string $path): string
    {
        if (! $path || ! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $path)) {
            return '/';
        }

        $route = parse_url($path, PHP_URL_PATH);
        foreach (['/login', '/register', '/forgot-password', '/reset-password', '/verify-email', '/auth/callback'] as $authRoute) {
            if ($route === $authRoute || str_starts_with($route, $authRoute.'/')) {
                return '/';
            }
        }

        return $path;
    }
}
