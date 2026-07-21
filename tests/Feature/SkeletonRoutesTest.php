<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SkeletonRoutesTest extends TestCase
{
    public function test_home_page_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Website học tiếng Anh');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_admin_placeholder_is_available(): void
    {
        // Tạo giả lập một user có role_id = 1 (Admin)
        $admin = User::factory()->make(['role_id' => 1]);

        // Đăng nhập tài khoản admin và truy cập đường dẫn /admin/dashboard
        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Khu vực quản trị')
            ->assertSee('chưa được triển khai');
    }
    public function test_api_status_endpoint_is_available(): void
    {
        $this->getJson('/api/status')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'version' => 'v1']);
    }
}
