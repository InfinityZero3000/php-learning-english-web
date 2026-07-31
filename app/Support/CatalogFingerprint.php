<?php

namespace App\Support;

use InvalidArgumentException;

final class CatalogFingerprint
{
    /** @var array<string, list<string>> */
    private const FIELDS = [
        'category' => ['name', 'slug', 'description', 'icon', 'color'],
        'course' => ['category_external_id', 'level', 'title', 'slug', 'description', 'status', 'language', 'thumbnail_url', 'estimated_duration', 'total_xp'],
        'unit' => ['course_external_id', 'title', 'description', 'sort_order', 'icon_url', 'background_color', 'status'],
        'lesson' => ['unit_external_id', 'title', 'slug', 'content', 'sort_order', 'status', 'lesson_type', 'estimated_minutes', 'xp_reward', 'pass_threshold', 'exercises'],
        'topic' => ['name', 'slug'],
        'vocabulary' => ['word', 'meaning', 'definition', 'translation', 'pronunciation', 'part_of_speech', 'difficulty_level', 'tags', 'example', 'external_audio_url', 'topic_external_ids'],
    ];

    /** @var list<string> */
    private const SET_FIELDS = ['tags', 'topic_external_ids'];

    public static function make(string $entity, array $data): string
    {
        $fields = self::FIELDS[$entity] ?? throw new InvalidArgumentException("Unsupported catalog entity [{$entity}].");
        $businessData = [];

        foreach ($fields as $field) {
            $businessData[$field] = self::normalize($data[$field] ?? null, $field);
        }

        return hash('sha256', json_encode(
            $businessData,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    private static function normalize(mixed $value, ?string $field = null): mixed
    {
        if (is_string($value)) {
            $value = str_replace(["\r\n", "\r"], "\n", $value);

            return preg_replace('/^\s+|\s+$/u', '', $value) ?? $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $value = array_map(fn (mixed $item): mixed => self::normalize($item), $value);

            if (in_array($field, self::SET_FIELDS, true)) {
                usort($value, fn (mixed $left, mixed $right): int => strcmp(
                    json_encode($left, JSON_THROW_ON_ERROR),
                    json_encode($right, JSON_THROW_ON_ERROR),
                ));
            }

            return $value;
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item, is_string($key) ? $key : null);
        }

        return $value;
    }
}
