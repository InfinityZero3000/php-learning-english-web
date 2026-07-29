<?php

namespace Tests\Feature;

use Tests\TestCase;

class RoleCapabilityTest extends TestCase
{
    public function test_legacy_admin_role_mutation_is_retired(): void
    {
        $this->assertContains($this->patch('/admin/users/1/role')->getStatusCode(), [404, 405]);
    }
}
