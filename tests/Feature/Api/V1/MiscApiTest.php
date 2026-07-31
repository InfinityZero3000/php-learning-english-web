<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiscApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lexilingo', [
            'backend_url' => 'https://backend.lexilingo.test',
            'ai_url' => 'https://ai.lexilingo.test',
            'partner_api_key' => 'partner-secret',
            'import_key' => 'import-secret',
            'ai_service_secret' => 'ai-secret',
            'timeout' => 30,
        ]);
    }

    public function test_root_info_endpoint_returns_app_metadata(): void
    {
        $this->getJson('/api/v1/')
            ->assertOk()
            ->assertJsonPath('version', 'v1')
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['name', 'version', 'status', 'health']);
    }

    public function test_content_news_and_youtube_proxy_upstream_success(): void
    {
        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/news*' => Http::response(['articles' => []]),
            'backend.lexilingo.test/api/v1/integrations/youtube/search*' => Http::response(['videos' => []]),
        ]);

        $this->getJson('/api/v1/content/news')->assertOk()->assertJsonPath('articles', []);
        $this->getJson('/api/v1/content/youtube?q=hello')->assertOk()->assertJsonPath('videos', []);
    }

    public function test_content_news_and_youtube_return_503_on_upstream_failure(): void
    {
        Http::fake([
            'backend.lexilingo.test/api/v1/integrations/news*' => Http::response(['error' => 'down'], 500),
            'backend.lexilingo.test/api/v1/integrations/youtube/search*' => Http::response(['error' => 'down'], 500),
        ]);

        $this->getJson('/api/v1/content/news')->assertStatus(503);
        $this->getJson('/api/v1/content/youtube')->assertStatus(503);
    }

    public function test_enrichment_word_requires_authentication(): void
    {
        $vocabulary = Vocabulary::create(['word' => 'test', 'meaning' => 'test']);

        $this->postJson("/api/v1/enrichment/words/{$vocabulary->id}")->assertUnauthorized();
    }

    public function test_enrichment_word_returns_404_for_nonexistent_vocabulary(): void
    {
        $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $this->actingAs(User::factory()->create(['role_id' => $admin->id]));

        $this->postJson('/api/v1/enrichment/words/999999')->assertNotFound();
    }

    public function test_learner_cannot_mutate_shared_vocabulary_through_enrichment(): void
    {
        $vocabulary = Vocabulary::create(['word' => 'test', 'meaning' => 'test']);
        $this->actingAs(User::factory()->create());

        $this->postJson("/api/v1/enrichment/words/{$vocabulary->id}")->assertForbidden();
    }

    public function test_admin_enrichment_marks_vocabulary_as_a_local_override(): void
    {
        config()->set('services.enrichment.enabled', true);
        $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $vocabulary = Vocabulary::create([
            'source_system' => 'lexilingo', 'external_id' => 'word-1', 'word' => 'hello', 'meaning' => 'hello',
        ]);

        $this->actingAs(User::factory()->create(['role_id' => $admin->id]))
            ->postJson("/api/v1/enrichment/words/{$vocabulary->id}")
            ->assertOk();

        $vocabulary->refresh();
        $this->assertSame(1, $vocabulary->catalog_revision);
        $this->assertNotNull($vocabulary->local_override_at);
    }
}
