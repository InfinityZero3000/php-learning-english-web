<?php

namespace App\Exceptions;

use RuntimeException;

class LessonQuizException extends RuntimeException
{
    public function __construct(public readonly string $apiCode, public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
