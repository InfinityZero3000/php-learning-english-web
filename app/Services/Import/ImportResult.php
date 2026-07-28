<?php

namespace App\Services\Import;

class ImportResult
{
    public function __construct(
        public readonly int $processed,
        public readonly int $skipped,
        public readonly int $nextCursor,
    ) {}
}
