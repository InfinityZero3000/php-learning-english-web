<?php

namespace App\Services\Import;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Groups the flat rows extracted from a CSV/XLSX/PDF file into a nested
 * course -> unit -> lesson -> [vocabulary] tree. Same grouping logic for
 * every source format — only FileCatalogRowExtractor differs per format.
 */
class FileCatalogTreeBuilder
{
    /** @var array<int, string> */
    public const COLUMNS = [
        'course_title', 'course_slug', 'unit_title', 'lesson_title', 'lesson_content',
        'word', 'meaning', 'pronunciation', 'part_of_speech', 'example',
    ];

    private const REQUIRED = ['course_title', 'unit_title', 'lesson_title'];

    /**
     * @param  array<int, array<int, string>>  $rawRows  header row first, then data rows
     * @return array{courses: array<int, array<string, mixed>>, unassignedErrors: array<int, string>}
     */
    public function build(array $rawRows): array
    {
        if ($rawRows === []) {
            throw new InvalidArgumentException('The file has no rows.');
        }

        $header = array_map(fn (string $cell): string => strtolower(trim($cell)), array_shift($rawRows));
        $missing = array_diff(self::COLUMNS, $header);
        $unknown = array_diff($header, self::COLUMNS);
        if ($missing !== [] || $unknown !== []) {
            throw new InvalidArgumentException(
                'Header must contain exactly these columns (any order): '.implode(', ', self::COLUMNS)
                .'. Missing: '.(implode(', ', $missing) ?: 'none')
                .'. Unknown: '.(implode(', ', $unknown) ?: 'none').'.',
            );
        }
        $index = array_flip($header);

        /** @var array<string, array<string, mixed>> $courses keyed by normalized title */
        $courses = [];
        $unassignedErrors = [];

        foreach ($rawRows as $offset => $row) {
            $lineNumber = $offset + 2; // +1 for header, +1 for 1-based line numbers
            $courseTitleRaw = isset($row[$index['course_title']]) ? trim((string) $row[$index['course_title']]) : '';

            if (array_filter($row, fn (string $cell): bool => $cell !== '') === []) {
                continue; // fully blank line, not an error
            }

            if (count($row) !== count($header)) {
                $this->recordError($courses, $unassignedErrors, $courseTitleRaw,
                    "Row {$lineNumber}: expected ".count($header).' columns, got '.count($row).'.');

                continue;
            }

            $cols = array_combine($header, $row);
            $missingRequired = array_filter(self::REQUIRED, fn (string $field): bool => trim($cols[$field]) === '');
            if ($missingRequired !== []) {
                $this->recordError($courses, $unassignedErrors, $courseTitleRaw,
                    "Row {$lineNumber}: missing required field(s): ".implode(', ', $missingRequired).'.');

                continue;
            }

            $courseKey = $this->normalize($cols['course_title']);
            $course = &$this->getOrCreate($courses, $courseKey, fn (): array => [
                'title' => trim($cols['course_title']),
                'slug' => trim($cols['course_slug']) !== '' ? trim($cols['course_slug']) : Str::slug($cols['course_title']),
                'units' => [],
                'errors' => [],
            ]);

            $unitKey = $this->normalize($cols['unit_title']);
            $unit = &$this->getOrCreate($course['units'], $unitKey, fn (): array => [
                'title' => trim($cols['unit_title']),
                'lessons' => [],
            ]);

            $lessonKey = $this->normalize($cols['lesson_title']);
            $lesson = &$this->getOrCreate($unit['lessons'], $lessonKey, fn (): array => [
                // First row for this lesson wins the content; later duplicate
                // rows only ever add vocabulary (see class docblock).
                'title' => trim($cols['lesson_title']),
                'content' => trim($cols['lesson_content']),
                'vocabulary' => [],
            ]);

            $word = trim($cols['word']);
            if ($word !== '') {
                $lesson['vocabulary'][] = [
                    'word' => $word,
                    'meaning' => trim($cols['meaning']),
                    'pronunciation' => trim($cols['pronunciation']) ?: null,
                    'part_of_speech' => trim($cols['part_of_speech']) ?: null,
                    'example' => trim($cols['example']) ?: null,
                ];
            }
            unset($lesson, $unit, $course);
        }

        return ['courses' => $this->reindex($courses), 'unassignedErrors' => $unassignedErrors];
    }

    /**
     * Courses/units/lessons are built as maps keyed by normalized title (so
     * lookups during grouping are O(1)); re-index them to plain lists before
     * handing the tree back, since that's what gets stored as JSON and
     * rendered by the admin UI.
     *
     * @param  array<string, array<string, mixed>>  $courses
     * @return array<int, array<string, mixed>>
     */
    private function reindex(array $courses): array
    {
        return array_values(array_map(function (array $course): array {
            $course['units'] = array_values(array_map(function (array $unit): array {
                $unit['lessons'] = array_values($unit['lessons']);

                return $unit;
            }, $course['units']));

            return $course;
        }, $courses));
    }

    /**
     * @param  array<string, array<string, mixed>>  $bucket
     * @param  callable(): array<string, mixed>  $make
     * @return array<string, mixed>
     */
    private function &getOrCreate(array &$bucket, string $key, callable $make): array
    {
        if (! isset($bucket[$key])) {
            $bucket[$key] = $make();
        }

        return $bucket[$key];
    }

    /**
     * A row that fails validation still names its course when the
     * course_title cell itself was readable, so the error surfaces next to
     * the course an admin would expect it under instead of getting lost.
     *
     * @param  array<string, array<string, mixed>>  $courses
     * @param  array<int, string>  $unassignedErrors
     */
    private function recordError(array &$courses, array &$unassignedErrors, string $courseTitleRaw, string $message): void
    {
        if ($courseTitleRaw === '') {
            $unassignedErrors[] = $message;

            return;
        }

        $courseKey = $this->normalize($courseTitleRaw);
        $course = &$this->getOrCreate($courses, $courseKey, fn (): array => [
            'title' => $courseTitleRaw, 'slug' => Str::slug($courseTitleRaw), 'units' => [], 'errors' => [],
        ]);
        $course['errors'][] = $message;
        unset($course);
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}
