<?php

namespace App\Console\Commands;

use App\Services\LexiLingoVocabularySync;
use Illuminate\Console\Command;

class SyncLexiLingoVocabulary extends Command
{
    protected $signature = 'lexilingo:sync-vocabulary
        {--offset=0 : Upstream offset}
        {--limit=100 : Page size, maximum 100}
        {--dry-run : Validate only, no database writes}';

    protected $description = 'Persist LexiLingo vocabulary into the local database';

    public function handle(LexiLingoVocabularySync $sync): int
    {
        if (! config('features.lexilingo_import')) {
            $this->error('LexiLingo import is disabled.');

            return self::FAILURE;
        }

        $count = $sync->syncPage(
            (int) $this->option('offset'),
            (int) $this->option('limit'),
            (bool) $this->option('dry-run'),
        );
        $this->info("Synchronized {$count} vocabulary items.");

        return self::SUCCESS;
    }
}
