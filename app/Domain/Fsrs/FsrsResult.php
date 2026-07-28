<?php

namespace App\Domain\Fsrs;

final readonly class FsrsResult
{
    public function __construct(
        public FsrsCard $card,
        public int $elapsedDays,
        public int $scheduledDays,
        public string $algorithm = FsrsConfig::VERSION,
    ) {}
}
