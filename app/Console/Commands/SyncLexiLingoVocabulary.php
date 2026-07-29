<?php

namespace App\Console\Commands;

use App\Services\AdminImportRunner;
use App\Services\LexiLingoVocabularySync;
use Illuminate\Console\Command;

class SyncLexiLingoVocabulary extends Command
{
    protected $signature = 'lexilingo:sync-vocabulary
        {--offset=0 : Upstream offset}
        {--limit=100 : Page size, maximum 100}
        {--dry-run : Validate only, no database writes}';

    protected $description = 'Persist LexiLingo vocabulary into the local database';

    public function handle(LexiLingoVocabularySync $sync, AdminImportRunner $runner): int
    {
        if (! config('features.lexilingo_import')) {
            $this->error('LexiLingo import is disabled.');

            return self::FAILURE;
        }

        $offset = (int) $this->option('offset');
        $limit = (int) $this->option('limit');
        if ((bool) $this->option('dry-run')) {
            $count = $sync->syncPage($offset, $limit, true);
            $this->info("Validated {$count} vocabulary items (dry-run).");
        } else {
            $run = $runner->runCli('vocabulary', $limit, cursor: $offset);
            $this->info("Vocabulary run={$run->id} status={$run->status}; admin review required.");
        }

        return self::SUCCESS;
    }
}
