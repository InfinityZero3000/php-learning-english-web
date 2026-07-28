<?php

namespace App\Domain\Fsrs;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class FsrsCard
{
    public function __construct(
        public string $state = 'learning',
        public ?int $step = 0,
        public ?float $stability = null,
        public ?float $difficulty = null,
        public ?DateTimeImmutable $due = null,
        public ?DateTimeImmutable $lastReview = null,
    ) {
        foreach (array_filter([$this->due, $this->lastReview]) as $date) {
            if ($date->getOffset() !== 0) {
                throw new InvalidArgumentException('FSRS timestamps must use UTC.');
            }
        }
    }

    public static function new(DateTimeImmutable $now): self
    {
        return new self(due: $now->setTimezone(new DateTimeZone('UTC')));
    }
}
