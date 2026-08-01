<?php

namespace Tests\Unit;

use App\Services\Import\FileCatalogTreeBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FileCatalogTreeBuilderTest extends TestCase
{
    private const HEADER = [
        'course_title', 'course_slug', 'unit_title', 'lesson_title', 'lesson_content',
        'word', 'meaning', 'pronunciation', 'part_of_speech', 'example',
    ];

    public function test_duplicate_lesson_rows_merge_vocabulary_and_first_row_content_wins(): void
    {
        $rows = [
            self::HEADER,
            ['Everyday English', '', 'Greetings', 'Saying Hello', 'First content wins.', 'hello', 'a greeting', '', 'interjection', 'Hello!'],
            ['Everyday English', '', 'Greetings', 'Saying Hello', 'This content is ignored.', 'goodbye', 'a farewell', '', 'interjection', 'Bye!'],
            ['everyday english', '', 'greetings', 'saying hello', '', 'hi', 'informal greeting', '', 'interjection', 'Hi!'],
        ];

        $tree = (new FileCatalogTreeBuilder)->build($rows);

        $this->assertSame([], $tree['unassignedErrors']);
        $this->assertCount(1, $tree['courses']);
        $course = $tree['courses'][0];
        $this->assertSame('Everyday English', $course['title']);
        $this->assertSame('everyday-english', $course['slug']);
        $this->assertCount(1, $course['units']);
        $this->assertCount(1, $course['units'][0]['lessons']);

        $lesson = $course['units'][0]['lessons'][0];
        $this->assertSame('First content wins.', $lesson['content']);
        $this->assertSame(['hello', 'goodbye', 'hi'], array_column($lesson['vocabulary'], 'word'));
    }

    public function test_row_without_a_word_still_contributes_lesson_structure(): void
    {
        $rows = [
            self::HEADER,
            ['Course A', '', 'Unit A', 'Lesson A', 'Just structure, no words.', '', '', '', '', ''],
        ];

        $tree = (new FileCatalogTreeBuilder)->build($rows);

        $lesson = $tree['courses'][0]['units'][0]['lessons'][0];
        $this->assertSame([], $lesson['vocabulary']);
        $this->assertSame('Just structure, no words.', $lesson['content']);
    }

    public function test_row_missing_a_required_field_is_excluded_and_reported_under_its_course(): void
    {
        $rows = [
            self::HEADER,
            ['Course A', '', 'Unit A', '', '', 'word', 'meaning', '', '', ''], // missing lesson_title
            ['Course A', '', 'Unit A', 'Lesson A', '', 'ok', 'fine', '', '', ''],
        ];

        $tree = (new FileCatalogTreeBuilder)->build($rows);

        $this->assertSame([], $tree['unassignedErrors']);
        $course = $tree['courses'][0];
        $this->assertCount(1, $course['errors']);
        $this->assertStringContainsString('Row 2', $course['errors'][0]);
        $this->assertStringContainsString('lesson_title', $course['errors'][0]);
        // The valid row still lands in the tree — one bad row doesn't sink the file.
        $this->assertCount(1, $course['units'][0]['lessons']);
    }

    public function test_row_with_wrong_column_count_and_no_readable_course_is_unassigned(): void
    {
        $rows = [
            self::HEADER,
            ['', '', 'columns'], // course_title cell itself is blank: nothing to attribute to
        ];

        $tree = (new FileCatalogTreeBuilder)->build($rows);

        $this->assertSame([], $tree['courses']);
        $this->assertCount(1, $tree['unassignedErrors']);
        $this->assertStringContainsString('expected 10 columns, got 3', $tree['unassignedErrors'][0]);
    }

    public function test_row_with_wrong_column_count_but_readable_course_title_is_attributed(): void
    {
        $rows = [
            self::HEADER,
            ['Course A', 'too', 'few'],
        ];

        $tree = (new FileCatalogTreeBuilder)->build($rows);

        $this->assertSame([], $tree['unassignedErrors']);
        $this->assertCount(1, $tree['courses']);
        $this->assertSame('Course A', $tree['courses'][0]['title']);
        $this->assertCount(1, $tree['courses'][0]['errors']);
        $this->assertSame([], $tree['courses'][0]['units']);
    }

    public function test_missing_or_unknown_header_column_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Missing: meaning/');

        (new FileCatalogTreeBuilder)->build([
            ['course_title', 'course_slug', 'unit_title', 'lesson_title', 'lesson_content', 'word', 'pronunciation', 'part_of_speech', 'example', 'extra_unknown_column'],
        ]);
    }

    public function test_header_is_case_insensitive_and_order_independent(): void
    {
        $reordered = array_reverse(self::HEADER);
        $upper = array_map('strtoupper', $reordered);
        $rowIndex = array_flip($upper);
        $row = array_fill(0, 10, '');
        $row[$rowIndex['COURSE_TITLE']] = 'Course A';
        $row[$rowIndex['UNIT_TITLE']] = 'Unit A';
        $row[$rowIndex['LESSON_TITLE']] = 'Lesson A';

        $tree = (new FileCatalogTreeBuilder)->build([$upper, $row]);

        $this->assertSame('Course A', $tree['courses'][0]['title']);
    }

    public function test_empty_file_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FileCatalogTreeBuilder)->build([]);
    }
}
