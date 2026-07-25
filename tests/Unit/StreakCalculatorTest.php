<?php

namespace Tests\Unit;

use App\Services\StreakCalculator;
use Tests\TestCase;

class StreakCalculatorTest extends TestCase
{
    public function test_it_counts_consecutive_days_from_today(): void
    {
        $this->assertSame(3, (new StreakCalculator)->calculate([now(), now()->subDay(), now()->subDays(2), now()->subDays(5)]));
    }
}
