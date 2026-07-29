<?php

namespace App\Console\Commands;

use App\Services\Import\AbstractLexiLingoImporter;
use App\Services\Import\CategoryImporter;
use App\Services\Import\CourseImporter;
use App\Services\Import\LessonContentImporter;
use App\Services\LexiLingoVocabularySync;
use Illuminate\Console\Command;

class ImportLexiLingoDataset extends Command
{
    protected $signature = 'lexilingo:import
        {entity : categories|courses|lessons|vocabulary|all}
        {--limit=50 : Page size per run}
        {--dry-run : Validate only, no database writes}
        {--reset : Ignore stored checkpoint and start from offset 0}';

    protected $description = 'Import category/course/unit/lesson/vocabulary data from LexiLingo';

    /** @var array<string, class-string<AbstractLexiLingoImporter>> */
    private array $importers = [
        'categories' => CategoryImporter::class,
        'courses' => CourseImporter::class,
        'lessons' => LessonContentImporter::class,
        'vocabulary' => LexiLingoVocabularySync::class,
    ];

    public function handle(): int
    {
        if (! config('features.lexilingo_import')) {
            $this->error('LexiLingo import is disabled.');

            return self::FAILURE;
        }

        $entity = $this->argument('entity');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $reset = (bool) $this->option('reset');

        $entities = $entity === 'all'
            ? array_keys($this->importers)
            : [$entity];

        foreach ($entities as $name) {
            if (! isset($this->importers[$name])) {
                $this->error("Unknown entity \"{$name}\". Expected one of: categories, courses, vocabulary, all.");

                return self::FAILURE;
            }
        }

        foreach ($entities as $name) {
            /** @var AbstractLexiLingoImporter $importer */
            $importer = $this->laravel->make($this->importers[$name]);

            $result = $importer->import($limit, $dryRun, $reset);

            $this->info(sprintf(
                '[%s] processed=%d skipped=%d next_cursor=%d%s',
                $name,
                $result->processed,
                $result->skipped,
                $result->nextCursor,
                $dryRun ? ' (dry-run)' : '',
            ));
        }

        return self::SUCCESS;
    }
}
