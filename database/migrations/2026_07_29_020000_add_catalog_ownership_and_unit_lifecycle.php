<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $tables = [
        'course_categories' => 'course_categories_source_external_unique',
        'courses' => 'courses_source_external_unique',
        'units' => 'units_source_external_unique',
        'lessons' => 'lessons_source_external_unique',
        'topics' => 'topics_source_external_unique',
        'vocabularies' => 'vocabularies_source_external_unique',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['external_id']);
            });

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->string('source_system')->default('local');
                $table->string('source_fingerprint', 64)->nullable();
                $table->json('source_snapshot')->nullable();
                $table->timestamp('local_override_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->unsignedBigInteger('catalog_revision')->default(0);
                $table->unique(['source_system', 'external_id'], $indexName);
            });

            DB::table($tableName)
                ->whereNotNull('external_id')
                ->update(['source_system' => 'lexilingo']);
        }

        Schema::table('units', function (Blueprint $table): void {
            $table->string('status')->default('draft');
        });

        DB::table('units')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('courses')
                    ->join('lessons', 'lessons.course_id', '=', 'courses.id')
                    ->whereColumn('courses.id', 'units.course_id')
                    ->whereColumn('lessons.unit_id', 'units.id')
                    ->where('courses.status', 'published')
                    ->where('lessons.status', 'published');
            })
            ->update(['status' => 'published']);
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $tableName) {
            $metadataHasBeenUsed = DB::table($tableName)
                ->where(function ($query): void {
                    $query->whereNotNull('source_fingerprint')
                        ->orWhereNotNull('source_snapshot')
                        ->orWhereNotNull('local_override_at')
                        ->orWhereNotNull('last_synced_at')
                        ->orWhere('catalog_revision', '>', 0)
                        ->orWhere(function ($query): void {
                            $query->whereNotNull('external_id')
                                ->where('source_system', '!=', 'lexilingo');
                        });
                })
                ->exists();

            if ($metadataHasBeenUsed) {
                throw new LogicException("Cannot roll back catalog ownership after {$tableName} ownership metadata has been used.");
            }

            $hasCrossSourceDuplicate = DB::table($tableName)
                ->select('external_id')
                ->whereNotNull('external_id')
                ->groupBy('external_id')
                ->havingRaw('COUNT(DISTINCT source_system) > 1')
                ->exists();

            if ($hasCrossSourceDuplicate) {
                throw new LogicException("Cannot roll back catalog ownership: {$tableName} contains cross-source external IDs.");
            }
        }

        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        foreach ($this->tables as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropUnique($indexName);
                $table->dropColumn([
                    'source_system',
                    'source_fingerprint',
                    'source_snapshot',
                    'local_override_at',
                    'last_synced_at',
                    'catalog_revision',
                ]);
                $table->unique('external_id');
            });
        }
    }
};
