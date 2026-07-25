<?php

namespace App\Console\Commands;

use App\Services\LexiLingoVocabularySync;
use Illuminate\Console\Command;

class SyncLexiLingoVocabulary extends Command
{
    protected $signature = 'lexilingo:sync-vocabulary
        {--offset=0 : Upstream offset}
        {--limit=100 : Page size, maximum 100}';

    protected $description = 'Persist LexiLingo vocabulary into the local database';

    public function handle(LexiLingoVocabularySync $sync): int
    {
        $count = $sync->syncPage((int) $this->option('offset'), (int) $this->option('limit'));
        $this->info("Synchronized {$count} vocabulary items.");

        return self::SUCCESS;
    }
}
