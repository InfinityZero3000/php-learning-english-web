<?php

namespace Tests\Unit;

use App\Support\AdminGoogleAccess;
use Tests\TestCase;

class AdminGoogleAccessTest extends TestCase
{
    public function test_super_admin_precedes_admin_and_matching_is_case_insensitive(): void
    {
        config()->set('admin_access.admin_emails', ['owner@example.com', 'admin@example.com']);
        config()->set('admin_access.super_admin_emails', ['owner@example.com']);

        $access = app(AdminGoogleAccess::class);

        $this->assertSame('super_admin', $access->role(' OWNER@EXAMPLE.COM '));
        $this->assertSame('admin', $access->role('admin@example.com'));
        $this->assertNull($access->role('other@example.com'));
    }

    public function test_missing_or_malformed_configuration_fails_closed(): void
    {
        config()->set('admin_access.admin_emails', []);
        config()->set('admin_access.super_admin_emails', []);
        $this->assertFalse(app(AdminGoogleAccess::class)->validConfiguration());

        config()->set('admin_access.admin_emails', ['not-an-email']);
        $this->assertNull(app(AdminGoogleAccess::class)->role('admin@example.com'));
    }
}
