<?php

namespace Tests\Unit;

use App\Services\Import\FileCatalogRowExtractor;
use PHPUnit\Framework\TestCase;

class FileCatalogRowExtractorTest extends TestCase
{
    public function test_it_reads_rows_from_an_xlsx_fixture(): void
    {
        $rows = (new FileCatalogRowExtractor)->extract(
            __DIR__.'/../Fixtures/file_catalog_import_sample.xlsx', 'xlsx',
        );

        $this->assertSame([
            'course_title', 'course_slug', 'unit_title', 'lesson_title', 'lesson_content',
            'word', 'meaning', 'pronunciation', 'part_of_speech', 'example',
        ], $rows[0]);
        $this->assertSame('XLSX Course', $rows[1][0]);
        $this->assertSame('apple', $rows[1][5]);
    }

    public function test_it_splits_pipe_delimited_text_lines_from_a_text_layer_pdf_fixture(): void
    {
        $rows = (new FileCatalogRowExtractor)->extract(
            __DIR__.'/../Fixtures/file_catalog_import_sample.pdf', 'pdf',
        );

        $this->assertSame('course_title', $rows[0][0]);
        $this->assertSame('PDF Course', $rows[1][0]);
        $this->assertSame('banana', $rows[1][5]);
        $this->assertSame('a yellow fruit', $rows[1][6]);
    }
}
