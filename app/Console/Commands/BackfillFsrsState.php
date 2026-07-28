<?php

namespace App\Console\Commands;

use App\Domain\Fsrs\FsrsCard;
use App\Domain\Fsrs\FsrsConfig;
use App\Domain\Fsrs\FsrsScheduler;
use App\Models\UserVocabulary;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFsrsState extends Command
{
    protected $signature = 'fsrs:backfill {--chunk=100}';

    protected $description = 'Backfill vocabulary scheduling state using the pinned FSRS-6 algorithm';

    public function handle(FsrsScheduler $scheduler): int
    {
        $processed = 0;

        UserVocabulary::query()
            ->where(function ($query): void {
                $query->whereNull('algorithm_version')
                    ->orWhere('algorithm_version', '!=', FsrsConfig::VERSION);
            })
            ->orderBy('id')
            ->chunkById(max(1, (int) $this->option('chunk')), function ($rows) use ($scheduler, &$processed): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, $scheduler, &$processed): void {
                        $locked = UserVocabulary::query()->lockForUpdate()->findOrFail($row->id);
                        if ($locked->algorithm_version === FsrsConfig::VERSION) {
                            return;
                        }

                        $reviews = $locked->reviews()->orderBy('reviewed_at')->orderBy('id')->get();
                        $now = now('UTC')->toImmutable();
                        $card = FsrsCard::new(new DateTimeImmutable(
                            ($reviews->first()?->reviewed_at ?? $now)->format('c'),
                            new DateTimeZone('UTC'),
                        ));
                        $result = null;

                        foreach ($reviews as $review) {
                            $result = $scheduler->review(
                                $card,
                                (int) $review->rating,
                                new DateTimeImmutable($review->reviewed_at->clone()->utc()->format('c')),
                            );
                            $card = $result->card;
                        }

                        $locked->forceFill([
                            'state' => $card->state,
                            'due_at' => $card->due,
                            'stability' => $card->stability,
                            'difficulty' => $card->difficulty,
                            'scheduled_days' => $result?->scheduledDays ?? 0,
                            'elapsed_days' => $result?->elapsedDays ?? 0,
                            'last_reviewed_at' => $card->lastReview,
                            'algorithm' => 'fsrs-6',
                            'algorithm_version' => FsrsConfig::VERSION,
                            'revision' => $reviews->count(),
                        ])->save();
                        $processed++;
                    });
                }
            });

        $this->info("Backfilled {$processed} vocabulary states.");

        return self::SUCCESS;
    }
}
