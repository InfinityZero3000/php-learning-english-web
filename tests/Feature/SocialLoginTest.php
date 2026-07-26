<?php

namespace Tests\Feature;

use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    public function test_legacy_social_routes_are_retired(): void
    {
        foreach (['/auth/google', '/auth/google/callback', '/auth/facebook', '/auth/facebook/callback'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
