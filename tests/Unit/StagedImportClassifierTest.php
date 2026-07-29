<?php

namespace Tests\Unit;

use App\Services\Import\StagedImportClassifier;
use App\Support\CatalogFingerprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StagedImportClassifierTest extends TestCase
{
    #[DataProvider('classifications')]
    public function test_classifies_without_writes(
        ?array $existing,
        array $errors,
        array $naturalMatches,
        string $classification,
        string $action,
    ): void {
        $candidate = ['title' => 'Local-first course', 'slug' => 'local-first'];
        $result = (new StagedImportClassifier)->classify(
            entity: 'course',
            candidate: $candidate,
            existing: $existing,
            validationErrors: $errors,
            naturalKeyMatches: $naturalMatches,
        );

        $this->assertSame($classification, $result['classification']);
        $this->assertSame($action, $result['selected_action']);
        $this->assertSame(CatalogFingerprint::make('course', $candidate), $result['normalized_fingerprint']);
    }

    public function test_captures_base_state_without_transport_or_http_concerns(): void
    {
        $candidate = ['title' => 'Updated', 'slug' => 'updated'];
        $existing = [
            'id' => 17,
            'catalog_revision' => 8,
            'source_fingerprint' => str_repeat('a', 64),
            'source_snapshot' => ['title' => 'Before', 'slug' => 'before'],
            'local_override_at' => '2026-07-29T10:00:00Z',
        ];

        $result = (new StagedImportClassifier)->classify('course', $candidate, $existing);

        $this->assertSame(17, $result['target_id']);
        $this->assertSame(8, $result['base_revision']);
        $this->assertSame(str_repeat('a', 64), $result['base_fingerprint']);
        $this->assertSame($existing['source_snapshot'], $result['base_snapshot']);
        $this->assertSame('2026-07-29T10:00:00Z', $result['base_override_at']);
        $this->assertSame('local_conflict', $result['classification']);
    }

    public function test_natural_key_ambiguity_is_invalid_and_never_auto_merged(): void
    {
        $result = (new StagedImportClassifier)->classify(
            entity: 'topic',
            candidate: ['name' => 'Travel', 'slug' => 'travel'],
            naturalKeyMatches: [['id' => 1], ['id' => 2]],
        );

        $this->assertSame('invalid', $result['classification']);
        $this->assertSame('exclude', $result['selected_action']);
        $this->assertContains('natural_key_ambiguous', $result['validation_errors']);
        $this->assertNull($result['target_id']);
    }

    public function test_stable_source_identity_wins_before_natural_key_checks(): void
    {
        $candidate = ['name' => 'Travel', 'slug' => 'travel'];
        $result = (new StagedImportClassifier)->classify(
            entity: 'topic',
            candidate: $candidate,
            existing: [
                'id' => 3,
                'catalog_revision' => 1,
                'source_fingerprint' => CatalogFingerprint::make('topic', $candidate),
            ],
            naturalKeyMatches: [['id' => 8], ['id' => 9]],
        );

        $this->assertSame('exact_duplicate', $result['classification']);
        $this->assertSame(3, $result['target_id']);
        $this->assertSame([], $result['validation_errors']);
    }

    public static function classifications(): array
    {
        $candidateFingerprint = CatalogFingerprint::make('course', [
            'title' => 'Local-first course',
            'slug' => 'local-first',
        ]);
        $base = [
            'id' => 4,
            'catalog_revision' => 2,
            'source_snapshot' => ['title' => 'Old'],
            'local_override_at' => null,
        ];

        return [
            'new' => [null, [], [], 'new', 'add'],
            'exact duplicate' => [[...$base, 'source_fingerprint' => $candidateFingerprint], [], [], 'exact_duplicate', 'skip'],
            'upstream update' => [[...$base, 'source_fingerprint' => str_repeat('b', 64)], [], [], 'upstream_update', 'replace'],
            'local conflict' => [[...$base, 'source_fingerprint' => str_repeat('b', 64), 'local_override_at' => '2026-07-29T09:00:00Z'], [], [], 'local_conflict', 'keep_local'],
            'invalid payload' => [null, ['title is required'], [], 'invalid', 'exclude'],
            'natural collision' => [null, [], [['id' => 9]], 'invalid', 'exclude'],
        ];
    }
}
