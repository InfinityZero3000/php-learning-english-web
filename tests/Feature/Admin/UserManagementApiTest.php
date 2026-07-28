<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Models\VocabularyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
            ...$attributes,
        ]);
    }

    private function learner(array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'learner')->value('id'),
            ...$attributes,
        ]);
    }

    public function test_list_supports_name_or_email_search(): void
    {
        $this->seed();
        $admin = $this->admin();
        $this->learner(['name' => 'Alice Nguyen', 'email' => 'alice@example.com']);
        $this->learner(['name' => 'Bob Tran', 'email' => 'bob@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/admin/users?email=alice');

        $response->assertOk();
        $names = collect($response->json('users'))->pluck('name');
        $this->assertTrue($names->contains('Alice Nguyen'));
        $this->assertFalse($names->contains('Bob Tran'));
    }

    public function test_list_filters_by_role_and_status(): void
    {
        $this->seed();
        $admin = $this->admin();
        $lockedLearner = $this->learner(['locked_at' => now()]);
        $this->learner();

        $lockedResponse = $this->actingAs($admin)->getJson('/api/admin/users?status=locked');
        $lockedIds = collect($lockedResponse->json('users'))->pluck('id');
        $this->assertTrue($lockedIds->contains($lockedLearner->id));
        $this->assertCount(1, $lockedIds);

        $adminRoleResponse = $this->actingAs($admin)->getJson('/api/admin/users?role=ADMIN');
        $adminRoleNames = collect($adminRoleResponse->json('users'))->pluck('role')->unique();
        $this->assertEquals(['ADMIN'], $adminRoleNames->values()->all());

        $moderatorResponse = $this->actingAs($admin)->getJson('/api/admin/users?role=MODERATOR');
        $moderatorResponse->assertOk();
        $this->assertCount(0, $moderatorResponse->json('users'));
    }

    public function test_list_pagination_is_zero_indexed(): void
    {
        $this->seed();
        $admin = $this->admin();
        User::factory()->count(3)->create(['role_id' => Role::where('slug', 'learner')->value('id')]);

        $response = $this->actingAs($admin)->getJson('/api/admin/users?size=2&page=0');

        $response->assertOk();
        $this->assertSame(0, $response->json('page'));
        $this->assertSame(2, $response->json('size'));
        $this->assertCount(2, $response->json('users'));
        $this->assertGreaterThanOrEqual(1, $response->json('totalPages'));
    }

    public function test_show_returns_real_stats_and_stubbed_streak_and_decks(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();
        $vocabulary = Vocabulary::create(['word' => 'ephemeral', 'meaning' => 'lasting a short time']);
        $userVocabulary = UserVocabulary::create([
            'user_id' => $learner->id,
            'vocabulary_id' => $vocabulary->id,
            'state' => 'review',
        ]);
        VocabularyReview::create([
            'user_vocabulary_id' => $userVocabulary->id,
            'rating' => 3,
            'stability' => 1,
            'difficulty' => 1,
            'scheduled_days' => 1,
            'elapsed_days' => 0,
            'reviewed_at' => now(),
        ]);
        VocabularyReview::create([
            'user_vocabulary_id' => $userVocabulary->id,
            'rating' => 1,
            'stability' => 1,
            'difficulty' => 1,
            'scheduled_days' => 1,
            'elapsed_days' => 0,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/users/{$learner->id}");

        $response->assertOk();
        $response->assertJsonPath('user.id', $learner->id);
        $response->assertJsonPath('stats.totalWords', 1);
        $response->assertJsonPath('stats.mastered', 1);
        $response->assertJsonPath('stats.correctAnswers', 1);
        $response->assertJsonPath('stats.incorrectAnswers', 1);
        $response->assertJsonPath('stats.accuracy', 50);
        $response->assertJsonPath('decks', []);
        $response->assertJsonPath('streak.currentStreak', 0);
    }

    public function test_history_orders_by_reviewed_at_desc_and_maps_correct_flag(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();
        $vocabulary = Vocabulary::create(['word' => 'ephemeral', 'meaning' => 'lasting a short time']);
        $userVocabulary = UserVocabulary::create(['user_id' => $learner->id, 'vocabulary_id' => $vocabulary->id]);
        VocabularyReview::create([
            'user_vocabulary_id' => $userVocabulary->id, 'rating' => 1, 'stability' => 1, 'difficulty' => 1,
            'scheduled_days' => 1, 'elapsed_days' => 0, 'reviewed_at' => now()->subDay(),
        ]);
        VocabularyReview::create([
            'user_vocabulary_id' => $userVocabulary->id, 'rating' => 4, 'stability' => 1, 'difficulty' => 1,
            'scheduled_days' => 1, 'elapsed_days' => 0, 'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/users/{$learner->id}/history?limit=5");

        $response->assertOk();
        $items = $response->json();
        $this->assertCount(2, $items);
        $this->assertSame(4, $items[0]['rating']);
        $this->assertTrue($items[0]['correct']);
        $this->assertSame(1, $items[1]['rating']);
        $this->assertFalse($items[1]['correct']);
        $this->assertSame('ephemeral', $items[0]['word']);
    }

    public function test_lock_and_unlock_a_learner(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();
        DB::table('sessions')->insert([
            'id' => 'learner-session',
            'user_id' => $learner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/lock")
            ->assertOk()->assertJsonPath('locked', true);
        $this->assertNotNull($learner->fresh()->locked_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'learner-session']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_LOCKED', 'actor_email' => $admin->email]);

        // Idempotent re-lock does not duplicate the audit entry.
        $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/lock")->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'USER_LOCKED')->count());

        $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/unlock")
            ->assertOk()->assertJsonPath('locked', false);
        $this->assertNull($learner->fresh()->locked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_UNLOCKED', 'actor_email' => $admin->email]);
    }

    public function test_lock_rolls_back_when_audit_write_fails(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();
        AuditLog::creating(fn () => throw new \RuntimeException('audit unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/lock");
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertNull($learner->fresh()->locked_at);
    }

    public function test_admin_cannot_lock_own_account(): void
    {
        $this->seed();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson("/api/admin/users/{$admin->id}/lock")
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->assertNull($admin->fresh()->locked_at);
    }

    public function test_locking_the_last_active_admin_is_blocked(): void
    {
        $this->seed();
        // CatalogSeeder also creates a default admin@example.com — lock it
        // too so the test's "only one active admin left" premise holds.
        User::where('email', 'admin@example.com')->update(['locked_at' => now()]);
        $staleAdminActor = $this->admin(['locked_at' => now()]);
        $onlyActiveAdmin = $this->admin();

        $this->actingAs($staleAdminActor)->putJson("/api/admin/users/{$onlyActiveAdmin->id}/lock")
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->assertNull($onlyActiveAdmin->fresh()->locked_at);
    }

    public function test_reset_password_returns_temporary_password_and_it_authenticates(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();

        $response = $this->actingAs($admin)->postJson("/api/admin/users/{$learner->id}/reset-password");

        $response->assertOk();
        $temp = $response->json('temporaryPassword');
        $this->assertIsString($temp);
        $this->assertTrue(Hash::check($temp, $learner->fresh()->password));

        $log = AuditLog::where('action', 'PASSWORD_RESET')->firstOrFail();
        $this->assertStringNotContainsString($temp, (string) $log->detail);
    }

    public function test_update_role_promotes_and_demotes(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/role", ['role' => 'ADMIN'])
            ->assertOk()->assertJsonPath('role', 'ADMIN');
        $this->assertSame(Role::where('slug', 'admin')->value('id'), $learner->fresh()->role_id);

        $this->actingAs($admin)->putJson("/api/admin/users/{$otherAdmin->id}/role", ['role' => 'USER'])
            ->assertOk()->assertJsonPath('role', 'USER');
        $this->assertSame(Role::where('slug', 'learner')->value('id'), $otherAdmin->fresh()->role_id);

        $this->assertSame(2, AuditLog::where('action', 'ROLE_CHANGED')->count());
    }

    public function test_admin_cannot_self_demote(): void
    {
        $this->seed();
        $admin = $this->admin();
        $this->admin(); // second admin so "last admin" isn't the only guard in play

        $this->actingAs($admin)->putJson("/api/admin/users/{$admin->id}/role", ['role' => 'USER'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertSame(Role::where('slug', 'admin')->value('id'), $admin->fresh()->role_id);
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $this->seed();
        // Make the created admin the genuine sole admin — CatalogSeeder also
        // creates a default admin@example.com.
        User::where('email', 'admin@example.com')->delete();
        $onlyAdmin = $this->admin();

        $this->actingAs($onlyAdmin)->putJson("/api/admin/users/{$onlyAdmin->id}/role", ['role' => 'USER'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_demoting_unrelated_learner_succeeds_when_actor_is_sole_admin(): void
    {
        // Regression test: the ported guard must check the *target* is an
        // admin before applying the last-admin protection, unlike the
        // original Blade controller which false-blocked this case.
        $this->seed();
        User::where('email', 'admin@example.com')->delete();
        $onlyAdmin = $this->admin();
        $learner = $this->learner();

        $this->actingAs($onlyAdmin)->putJson("/api/admin/users/{$learner->id}/role", ['role' => 'USER'])
            ->assertOk();
    }

    public function test_moderator_role_is_rejected_on_assignment(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();

        $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/role", ['role' => 'MODERATOR'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_role_id_in_request_body_is_not_mass_assignable(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();
        $adminRoleId = Role::where('slug', 'admin')->value('id');

        $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/role", [
            'role' => 'USER',
            'role_id' => $adminRoleId,
        ])->assertOk();

        $this->assertSame(Role::where('slug', 'learner')->value('id'), $learner->fresh()->role_id);
    }

    public function test_audit_logs_are_listable(): void
    {
        $this->seed();
        $admin = $this->admin();
        $learner = $this->learner();

        $this->actingAs($admin)->putJson("/api/admin/users/{$learner->id}/lock")->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/admin/audit-logs');

        $response->assertOk();
        $entry = collect($response->json())->firstWhere('action', 'USER_LOCKED');
        $this->assertNotNull($entry);
        $this->assertSame($admin->email, $entry['actor']);
        $this->assertSame('ADMIN', $entry['actorRole']);
        $this->assertSame("User #{$learner->id}", $entry['resource']);
    }

    public static function protectedRoutesProvider(): array
    {
        return [
            'list' => ['GET', '/api/admin/users'],
            'show' => ['GET', '/api/admin/users/999999'],
            'history' => ['GET', '/api/admin/users/999999/history'],
            'lock' => ['PUT', '/api/admin/users/999999/lock'],
            'unlock' => ['PUT', '/api/admin/users/999999/unlock'],
            'resetPassword' => ['POST', '/api/admin/users/999999/reset-password'],
            'role' => ['PUT', '/api/admin/users/999999/role'],
            'auditLogs' => ['GET', '/api/admin/audit-logs'],
        ];
    }

    #[DataProvider('protectedRoutesProvider')]
    public function test_guest_is_rejected_with_401(string $method, string $uri): void
    {
        $this->seed();

        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('protectedRoutesProvider')]
    public function test_learner_is_rejected_with_403_even_for_nonexistent_target(string $method, string $uri): void
    {
        $this->seed();
        $learner = $this->learner();

        $this->actingAs($learner)->json($method, $uri)->assertForbidden();
    }

    public function test_learner_gets_same_403_for_existing_target_as_nonexistent(): void
    {
        // IDOR guard: a learner must not be able to distinguish an existing
        // user id from a nonexistent one via the admin routes.
        $this->seed();
        $learner = $this->learner();
        $otherLearner = $this->learner();

        $existing = $this->actingAs($learner)->getJson("/api/admin/users/{$otherLearner->id}");
        $nonexistent = $this->actingAs($learner)->getJson('/api/admin/users/999999');

        $existing->assertForbidden();
        $nonexistent->assertForbidden();
        $this->assertSame($existing->getStatusCode(), $nonexistent->getStatusCode());
    }
}
