<?php

namespace App\Domain\Fsrs;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class FsrsScheduler
{
    private const MIN_STABILITY = 0.001;

    public function review(FsrsCard $source, int $rating, DateTimeImmutable $now): FsrsResult
    {
        if ($rating < 1 || $rating > 4) {
            throw new InvalidArgumentException('FSRS rating must be between 1 and 4.');
        }
        if ($now->getOffset() !== 0) {
            throw new InvalidArgumentException('FSRS review time must use UTC.');
        }

        $card = clone $source;
        $elapsedDays = $card->lastReview
            ? max(0, (int) $card->lastReview->diff($now)->format('%r%a'))
            : 0;

        match ($card->state) {
            'learning' => $this->reviewLearning($card, $rating, $now, $elapsedDays),
            'review' => $this->reviewReview($card, $rating, $now, $elapsedDays),
            'relearning' => $this->reviewRelearning($card, $rating, $now, $elapsedDays),
            default => throw new InvalidArgumentException("Unknown FSRS state: {$card->state}"),
        };

        $scheduledDays = max(0, (int) $now->diff($card->due)->format('%r%a'));
        $card->lastReview = $now;

        return new FsrsResult($card, $elapsedDays, $scheduledDays);
    }

    public function retrievability(FsrsCard $card, DateTimeImmutable $now): float
    {
        if (! $card->lastReview || ! $card->stability) {
            return 0.0;
        }

        $elapsedDays = max(0, (int) $card->lastReview->diff($now)->format('%r%a'));
        $decay = -FsrsConfig::PARAMETERS[20];
        $factor = 0.9 ** (1 / $decay) - 1;

        return (1 + $factor * $elapsedDays / $card->stability) ** $decay;
    }

    private function reviewLearning(FsrsCard $card, int $rating, DateTimeImmutable $now, int $elapsedDays): void
    {
        if ($card->stability === null || $card->difficulty === null) {
            $card->stability = $this->initialStability($rating);
            $card->difficulty = $this->initialDifficulty($rating);
        } elseif ($elapsedDays < 1) {
            $card->stability = $this->shortTermStability($card->stability, $rating);
            $card->difficulty = $this->nextDifficulty($card->difficulty, $rating);
        } else {
            $this->updateLongTermMemory($card, $rating, $now);
        }

        $this->applySteps($card, $rating, $now, FsrsConfig::LEARNING_STEPS);
    }

    private function reviewReview(FsrsCard $card, int $rating, DateTimeImmutable $now, int $elapsedDays): void
    {
        if ($elapsedDays < 1) {
            $card->stability = $this->shortTermStability((float) $card->stability, $rating);
        } else {
            $card->stability = $this->nextStability(
                (float) $card->difficulty,
                (float) $card->stability,
                $this->retrievability($card, $now),
                $rating,
            );
        }
        $card->difficulty = $this->nextDifficulty((float) $card->difficulty, $rating);

        if ($rating === 1) {
            $card->state = 'relearning';
            $card->step = 0;
            $card->due = $now->add(new DateInterval('PT'.FsrsConfig::RELEARNING_STEPS[0].'S'));

            return;
        }

        $this->graduate($card, $now);
    }

    private function reviewRelearning(FsrsCard $card, int $rating, DateTimeImmutable $now, int $elapsedDays): void
    {
        if ($elapsedDays < 1) {
            $card->stability = $this->shortTermStability((float) $card->stability, $rating);
        } else {
            $card->stability = $this->nextStability(
                (float) $card->difficulty,
                (float) $card->stability,
                $this->retrievability($card, $now),
                $rating,
            );
        }
        $card->difficulty = $this->nextDifficulty((float) $card->difficulty, $rating);
        $this->applySteps($card, $rating, $now, FsrsConfig::RELEARNING_STEPS);
    }

    private function applySteps(FsrsCard $card, int $rating, DateTimeImmutable $now, array $steps): void
    {
        $step = $card->step ?? 0;
        if ($step >= count($steps) && $rating > 1) {
            $this->graduate($card, $now);

            return;
        }

        if ($rating === 1) {
            $card->step = 0;
            $seconds = $steps[0];
        } elseif ($rating === 2) {
            $seconds = $step === 0 && count($steps) >= 2
                ? (int) (($steps[0] + $steps[1]) / 2)
                : (int) ($steps[$step] * ($step === 0 && count($steps) === 1 ? 1.5 : 1));
        } elseif ($rating === 3 && $step + 1 < count($steps)) {
            $card->step = $step + 1;
            $seconds = $steps[$card->step];
        } else {
            $this->graduate($card, $now);

            return;
        }

        $card->due = $now->add(new DateInterval("PT{$seconds}S"));
    }

    private function graduate(FsrsCard $card, DateTimeImmutable $now): void
    {
        $card->state = 'review';
        $card->step = null;
        $card->due = $now->add(new DateInterval('P'.$this->nextInterval((float) $card->stability).'D'));
    }

    private function updateLongTermMemory(FsrsCard $card, int $rating, DateTimeImmutable $now): void
    {
        $card->stability = $this->nextStability(
            (float) $card->difficulty,
            (float) $card->stability,
            $this->retrievability($card, $now),
            $rating,
        );
        $card->difficulty = $this->nextDifficulty((float) $card->difficulty, $rating);
    }

    private function initialStability(int $rating): float
    {
        return max(FsrsConfig::PARAMETERS[$rating - 1], self::MIN_STABILITY);
    }

    private function initialDifficulty(int $rating, bool $clamp = true): float
    {
        $difficulty = FsrsConfig::PARAMETERS[4] - M_E ** (FsrsConfig::PARAMETERS[5] * ($rating - 1)) + 1;

        return $clamp ? $this->clampDifficulty($difficulty) : $difficulty;
    }

    private function nextDifficulty(float $difficulty, int $rating): float
    {
        $delta = -FsrsConfig::PARAMETERS[6] * ($rating - 3);
        $damped = (10 - $difficulty) * $delta / 9;
        $easy = $this->initialDifficulty(4, false);
        $next = FsrsConfig::PARAMETERS[7] * $easy
            + (1 - FsrsConfig::PARAMETERS[7]) * ($difficulty + $damped);

        return $this->clampDifficulty($next);
    }

    private function shortTermStability(float $stability, int $rating): float
    {
        $increase = M_E ** (FsrsConfig::PARAMETERS[17] * ($rating - 3 + FsrsConfig::PARAMETERS[18]))
            * $stability ** -FsrsConfig::PARAMETERS[19];
        if ($rating >= 3) {
            $increase = max($increase, 1.0);
        }

        return max($stability * $increase, self::MIN_STABILITY);
    }

    private function nextStability(float $difficulty, float $stability, float $retrievability, int $rating): float
    {
        if ($rating === 1) {
            $long = FsrsConfig::PARAMETERS[11]
                * $difficulty ** -FsrsConfig::PARAMETERS[12]
                * (($stability + 1) ** FsrsConfig::PARAMETERS[13] - 1)
                * M_E ** ((1 - $retrievability) * FsrsConfig::PARAMETERS[14]);
            $short = $stability / M_E ** (FsrsConfig::PARAMETERS[17] * FsrsConfig::PARAMETERS[18]);

            return max(min($long, $short), self::MIN_STABILITY);
        }

        $hardPenalty = $rating === 2 ? FsrsConfig::PARAMETERS[15] : 1;
        $easyBonus = $rating === 4 ? FsrsConfig::PARAMETERS[16] : 1;
        $next = $stability * (1
            + M_E ** FsrsConfig::PARAMETERS[8]
            * (11 - $difficulty)
            * $stability ** -FsrsConfig::PARAMETERS[9]
            * (M_E ** ((1 - $retrievability) * FsrsConfig::PARAMETERS[10]) - 1)
            * $hardPenalty
            * $easyBonus);

        return max($next, self::MIN_STABILITY);
    }

    private function nextInterval(float $stability): int
    {
        $decay = -FsrsConfig::PARAMETERS[20];
        $factor = 0.9 ** (1 / $decay) - 1;
        $interval = round(
            ($stability / $factor) * (FsrsConfig::DESIRED_RETENTION ** (1 / $decay) - 1),
            mode: PHP_ROUND_HALF_EVEN,
        );

        return (int) min(max($interval, 1), FsrsConfig::MAXIMUM_INTERVAL_DAYS);
    }

    private function clampDifficulty(float $difficulty): float
    {
        return min(max($difficulty, 1.0), 10.0);
    }
}
