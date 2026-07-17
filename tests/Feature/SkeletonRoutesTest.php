<?php

namespace Tests\Feature;

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
        $this->get('/admin')
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
