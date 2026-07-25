<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class StreakCalculator
{
    public function calculate(iterable $dates): int
    {
        $set = collect($dates)->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())->unique()->flip();
        $day = CarbonImmutable::today();
        $streak = 0;
        while ($set->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

return $streak;
    }
}
