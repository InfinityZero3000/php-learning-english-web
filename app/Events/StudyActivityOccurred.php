<?php

namespace App\Events;

class StudyActivityOccurred
{
    public function __construct(public int $userId, public string $type, public int $referenceId, public string $referenceType, public int $durationSeconds = 0) {}
}
