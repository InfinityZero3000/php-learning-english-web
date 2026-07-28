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
    }

    public static function fixtureCases(): array
    {
        $cases = json_decode(file_get_contents(__DIR__.'/../Fixtures/fsrs_v6_3_0.json'), true, flags: JSON_THROW_ON_ERROR);

        $datasets = [];
        foreach ($cases as $case) {
            $datasets[$case['name']] = [$case];
        }

        return $datasets;
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
