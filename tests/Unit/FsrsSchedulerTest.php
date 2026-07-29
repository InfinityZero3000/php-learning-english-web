<?php

namespace Tests\Unit;

use App\Domain\Fsrs\FsrsCard;
use App\Domain\Fsrs\FsrsScheduler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FsrsSchedulerTest extends TestCase
{
    #[DataProvider('fixtureCases')]
    public function test_matches_py_fsrs_6_3_0(array $case): void
    {
        $before = $case['before'];
        $card = new FsrsCard(
            state: $before['state'],
            step: $before['step'],
            stability: $before['stability'],
            difficulty: $before['difficulty'],
            due: new DateTimeImmutable($before['due']),
            lastReview: isset($before['last_review']) ? new DateTimeImmutable($before['last_review']) : null,
        );

        $result = (new FsrsScheduler)->review($card, $case['rating'], new DateTimeImmutable($case['at']));
        $expected = $case['after'];

        $this->assertSame($expected['state'], $result->card->state);
        $this->assertSame($expected['step'], $result->card->step);
        $this->assertSame($expected['due'], $result->card->due->format('c'));
        $this->assertSame($expected['last_review'], $result->card->lastReview->format('c'));
        $this->assertEqualsWithDelta($expected['stability'], $result->card->stability, 1e-9);
        $this->assertEqualsWithDelta($expected['difficulty'], $result->card->difficulty, 1e-9);
        $this->assertSame(
            max(0, (int) (new DateTimeImmutable($case['at']))->diff(new DateTimeImmutable($expected['due']))->format('%r%a')),
            $result->scheduledDays,
        );
        $this->assertSame($before['state'], $card->state);
        $this->assertSame($before['due'], $card->due->format('c'));
    }

    public static function fixtureCases(): array
    {
        $payload = json_decode(file_get_contents(__DIR__.'/../Fixtures/fsrs_v6_3_0.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('6.3.0', $payload['version']);

        $datasets = [];
        foreach ($payload['cases'] as $case) {
            $datasets[$case['name']] = [$case];
        }

        return $datasets;
    }

    public function test_uses_python_ties_to_even_interval_rounding(): void
    {
        $interval = (fn (float $stability): int => $this->nextInterval($stability))
            ->call(new FsrsScheduler, 2.5);

        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/fsrs_v6_3_0.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($fixture['interval_cases'][0]['days'], $interval);
        $this->assertSame(2, $interval);
    }

    public function test_rejects_non_utc_review_time(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FsrsScheduler)->review(
            FsrsCard::new(new DateTimeImmutable('2026-01-01T12:00:00+00:00')),
            3,
            new DateTimeImmutable('2026-01-01T19:00:00+07:00'),
        );
    }
}
