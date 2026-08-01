<?php

namespace App\Services\Import;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Turns an uploaded file into raw string rows (header row included, in file
 * order). Only "get the rows out of the file" lives here — grouping rows
 * into a course/unit/lesson tree is FileCatalogTreeBuilder's job, shared
 * across every format so csv/xlsx/xls/pdf all get identical validation.
 */
class FileCatalogRowExtractor
{
    /**
     * @return array<int, array<int, string>>
     */
    public function extract(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv', 'xlsx', 'xls' => $this->extractSpreadsheet($path),
            'pdf' => $this->extractPdf($path),
            default => throw new InvalidArgumentException("Unsupported file type: {$extension}"),
        };
    }

    /**
     * PhpSpreadsheet reads CSV and XLSX/XLS through the same API, so one
     * reader covers all three tabular formats — see FileCatalogImportController.
     *
     * @return array<int, array<int, string>>
     */
    private function extractSpreadsheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = [];
        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $row = array_map(fn (mixed $cell): string => trim((string) ($cell ?? '')), $row);
            if ($row === array_fill(0, count($row), '')) {
                continue; // blank row: common trailing artifact, not data
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * No OCR/table extraction — a text-layer PDF must contain the header
     * plus pipe-delimited rows as plain text lines (documented in the
     * downloadable template). Each line is split on '|' just like a CSV row.
     *
     * @return array<int, array<int, string>>
     */
    private function extractPdf(string $path): array
    {
        $text = (new PdfParser)->parseFile($path)->getText();
        $rows = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $rows[] = array_map('trim', explode('|', $line));
        }

        return $rows;
    }
}
