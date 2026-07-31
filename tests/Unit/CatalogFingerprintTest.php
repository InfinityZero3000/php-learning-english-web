<?php

namespace Tests\Unit;

use App\Support\CatalogFingerprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CatalogFingerprintTest extends TestCase
{
    public function test_it_is_stable_for_equivalent_business_data(): void
    {
        $first = [
            'word' => " hello\r\nworld ",
            'tags' => ['travel', 'basic'],
            'translation' => ['vi' => 'xin chào', 'fr' => 'bonjour'],
            'ignored_transport_field' => 'one',
        ];
        $second = [
            'translation' => ['fr' => 'bonjour', 'vi' => 'xin chào'],
            'tags' => ['basic', 'travel'],
            'word' => "hello\nworld",
            'ignored_transport_field' => 'two',
        ];

        $this->assertSame(
            CatalogFingerprint::make('vocabulary', $first),
            CatalogFingerprint::make('vocabulary', $second),
        );
    }

    public function test_it_changes_when_an_allowed_business_field_changes(): void
    {
        $base = ['title' => 'Unit one', 'sort_order' => 1];

        $this->assertNotSame(
            CatalogFingerprint::make('unit', $base),
            CatalogFingerprint::make('unit', [...$base, 'sort_order' => 2]),
        );
    }

    #[DataProvider('importerBusinessFields')]
    public function test_every_importer_business_field_participates_in_the_fingerprint(
        string $entity,
        string $field,
        mixed $value,
    ): void {
        $this->assertNotSame(
            CatalogFingerprint::make($entity, []),
            CatalogFingerprint::make($entity, [$field => $value]),
            "{$entity}.{$field} is missing from its fingerprint allowlist.",
        );
    }

    public function test_transport_fields_do_not_participate_in_the_fingerprint(): void
    {
        $this->assertSame(
            CatalogFingerprint::make('course', ['request_id' => 'first']),
            CatalogFingerprint::make('course', ['request_id' => 'second']),
        );
    }

    public static function importerBusinessFields(): array
    {
        return [
            ...self::fields('category', ['name', 'slug', 'description', 'icon', 'color']),
            ...self::fields('course', ['category_external_id', 'level', 'title', 'slug', 'description', 'status', 'language', 'thumbnail_url', 'estimated_duration', 'total_xp']),
            ...self::fields('unit', ['course_external_id', 'title', 'description', 'sort_order', 'icon_url', 'background_color', 'status']),
            ...self::fields('lesson', ['unit_external_id', 'title', 'slug', 'content', 'sort_order', 'status', 'lesson_type', 'estimated_minutes', 'xp_reward', 'pass_threshold', 'exercises']),
            ...self::fields('topic', ['name', 'slug']),
            ...self::fields('vocabulary', ['word', 'meaning', 'definition', 'translation', 'pronunciation', 'part_of_speech', 'difficulty_level', 'tags', 'example', 'external_audio_url', 'topic_external_ids']),
        ];
    }

    private static function fields(string $entity, array $fields): array
    {
        $datasets = [];

        foreach ($fields as $field) {
            $datasets["{$entity}.{$field}"] = [
                $entity,
                $field,
                in_array($field, ['tags', 'topic_external_ids'], true) ? ['changed'] : 'changed',
            ];
        }

        return $datasets;
    }
}
