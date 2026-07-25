<?php

namespace Tests\Feature\Api\V1;

use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VocabularyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_vocabulary_api_reads_local_persisted_data(): void
    {
        Vocabulary::create([
            'external_id' => 'lexi-word-1',
            'word' => 'hello',
            'meaning' => 'xin chào',
        ]);

        $this->getJson('/api/v1/vocabulary?search=hello')
            ->assertOk()
            ->assertJsonPath('data.0.external_id', 'lexi-word-1')
            ->assertJsonPath('meta.total', 1);
    }
}
