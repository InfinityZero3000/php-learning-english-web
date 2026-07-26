<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: already extended
        if (Schema::hasColumn('bookmarks', 'bookmark_type')) {
            return;
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            // SQLite does not support ALTER COLUMN, so rebuild the table
            // with vocabulary_id nullable (lesson bookmarks have vocabulary_id = null).

            // Step S1: Create new table with desired schema
            DB::statement('CREATE TABLE `bookmarks_new` (
                `id` integer primary key autoincrement,
                `user_id` integer not null,
                `vocabulary_id` integer null,
                `lesson_id` integer null,
                `bookmark_type` varchar not null default \'vocabulary\',
                `created_at` datetime null,
                `updated_at` datetime null,
                foreign key(`user_id`) references `users`(`id`) on delete cascade,
                foreign key(`vocabulary_id`) references `vocabularies`(`id`) on delete cascade,
                foreign key(`lesson_id`) references `lessons`(`id`) on delete cascade
            )');

            // Step S2: Copy existing rows (all are vocabulary bookmarks, vocabulary_id is NOT NULL)
            DB::statement('INSERT INTO `bookmarks_new` (`id`, `user_id`, `vocabulary_id`, `lesson_id`, `bookmark_type`, `created_at`, `updated_at`)
                SELECT `id`, `user_id`, `vocabulary_id`, NULL, \'vocabulary\', `created_at`, `updated_at`
                FROM `bookmarks`');

            // Step S3: Drop old table and rename
            DB::statement('DROP TABLE `bookmarks`');
            DB::statement('ALTER TABLE `bookmarks_new` RENAME TO `bookmarks`');

            // Step S4: Create indexes
            DB::statement('CREATE UNIQUE INDEX `bookmarks_user_id_vocabulary_id_bookmark_type_unique` ON `bookmarks` (`user_id`, `vocabulary_id`, `bookmark_type`)');
            DB::statement('CREATE UNIQUE INDEX `bookmarks_user_id_lesson_id_bookmark_type_unique` ON `bookmarks` (`user_id`, `lesson_id`, `bookmark_type`)');
        } else {
            // MySQL path: must carefully drop FK constraints before
            // altering unique indexes that share the same columns.

            // Step 1: Drop FK constraint on vocabulary_id
            try {
                DB::statement('ALTER TABLE `bookmarks` DROP FOREIGN KEY `bookmarks_vocabulary_id_foreign`');
            } catch (Throwable) {
            }

            // Step 2: Drop index that shares name with old FK
            try {
                DB::statement('ALTER TABLE `bookmarks` DROP INDEX `bookmarks_vocabulary_id_foreign`');
            } catch (Throwable) {
            }

            // Step 3: Drop user_id FK before dropping the unique index
            $droppedUserFk = false;
            if ($this->hasForeignKey('bookmarks', 'bookmarks_user_id_foreign')) {
                try {
                    DB::statement('ALTER TABLE `bookmarks` DROP FOREIGN KEY `bookmarks_user_id_foreign`');
                    $droppedUserFk = true;
                } catch (Throwable) {
                }
            }

            // Step 4: Drop old unique index (user_id, vocabulary_id)
            try {
                DB::statement('ALTER TABLE `bookmarks` DROP INDEX `bookmarks_user_id_vocabulary_id_unique`');
            } catch (Throwable) {
            }

            // Step 5: Restore user_id FK
            if ($droppedUserFk && ! $this->hasForeignKey('bookmarks', 'bookmarks_user_id_foreign')) {
                DB::statement('ALTER TABLE `bookmarks` ADD CONSTRAINT `bookmarks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
            }

            // Step 6: Make vocabulary_id nullable
            DB::statement('ALTER TABLE `bookmarks` MODIFY `vocabulary_id` BIGINT UNSIGNED NULL');

            // Step 7: Add lesson_id and bookmark_type
            Schema::table('bookmarks', function ($table) {
                $table->foreignId('lesson_id')->nullable()->after('vocabulary_id')->constrained()->cascadeOnDelete();
                $table->string('bookmark_type')->default('vocabulary')->after('lesson_id');
            });

            // Step 8: Re-create FK on vocabulary_id
            if (! $this->hasForeignKey('bookmarks', 'bookmarks_vocabulary_id_foreign')) {
                DB::statement('ALTER TABLE `bookmarks` ADD CONSTRAINT `bookmarks_vocabulary_id_foreign` FOREIGN KEY (`vocabulary_id`) REFERENCES `vocabularies` (`id`) ON DELETE CASCADE');
            }

            // Step 9: Create new composite unique indexes
            Schema::table('bookmarks', function ($table) {
                if (! $this->hasIndex('bookmarks', 'bookmarks_user_id_vocabulary_id_bookmark_type_unique')) {
                    $table->unique(['user_id', 'vocabulary_id', 'bookmark_type'], 'bookmarks_user_id_vocabulary_id_bookmark_type_unique');
                }
                if (! $this->hasIndex('bookmarks', 'bookmarks_user_id_lesson_id_bookmark_type_unique')) {
                    $table->unique(['user_id', 'lesson_id', 'bookmark_type'], 'bookmarks_user_id_lesson_id_bookmark_type_unique');
                }
            });
        }
    }

    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            // Drop ALL FK constraints on vocabulary_id column
            // (MySQL may have auto-generated names, so we query info schema)
            $dbName = DB::connection()->getDatabaseName();
            $fks = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $dbName)
                ->where('TABLE_NAME', 'bookmarks')
                ->where('COLUMN_NAME', 'vocabulary_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->pluck('CONSTRAINT_NAME');

            foreach ($fks as $fk) {
                try {
                    DB::statement("ALTER TABLE `bookmarks` DROP FOREIGN KEY `{$fk}`");
                } catch (Throwable) {
                }
            }
        }

        // Drop new unique indexes (wrapped in try-catch at Schema level)
        try {
            Schema::table('bookmarks', function ($table) use ($isSqlite) {
                if (! $isSqlite || $this->hasIndex('bookmarks', 'bookmarks_user_id_lesson_id_bookmark_type_unique')) {
                    $table->dropUnique('bookmarks_user_id_lesson_id_bookmark_type_unique');
                }
                if (! $isSqlite || $this->hasIndex('bookmarks', 'bookmarks_user_id_vocabulary_id_bookmark_type_unique')) {
                    $table->dropUnique('bookmarks_user_id_vocabulary_id_bookmark_type_unique');
                }
            });
        } catch (Throwable) {
        }

        // Drop lesson_id FK and column
        if (Schema::hasColumn('bookmarks', 'lesson_id')) {
            try {
                Schema::table('bookmarks', function ($table) {
                    $table->dropConstrainedForeignId('lesson_id');
                });
            } catch (Throwable) {
            }
        }

        // Drop bookmark_type column
        if (Schema::hasColumn('bookmarks', 'bookmark_type')) {
            try {
                Schema::table('bookmarks', function ($table) {
                    $table->dropColumn('bookmark_type');
                });
            } catch (Throwable) {
            }
        }

        if (! $isSqlite) {
            // Restore vocabulary_id as NOT NULL (MySQL only)
            try {
                DB::statement('ALTER TABLE `bookmarks` MODIFY `vocabulary_id` BIGINT UNSIGNED NOT NULL');
            } catch (Throwable) {
            }

            // Rebuild FK constraint
            try {
                if (! $this->hasForeignKey('bookmarks', 'bookmarks_vocabulary_id_foreign')) {
                    DB::statement('ALTER TABLE `bookmarks` ADD CONSTRAINT `bookmarks_vocabulary_id_foreign` FOREIGN KEY (`vocabulary_id`) REFERENCES `vocabularies` (`id`) ON DELETE CASCADE');
                }
            } catch (Throwable) {
            }
        }

        // Restore old unique index
        try {
            Schema::table('bookmarks', function ($table) {
                if (! $this->hasIndex('bookmarks', 'bookmarks_user_id_vocabulary_id_unique')) {
                    $table->unique(['user_id', 'vocabulary_id'], 'bookmarks_user_id_vocabulary_id_unique');
                }
            });
        } catch (Throwable) {
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        if ($this->isSqlite()) {
            return false; // SQLite builds fresh tables — indices don't pre-exist
        }

        try {
            $db = DB::connection()->getDatabaseName();

            return DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $index)
                ->count() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasForeignKey(string $table, string $fkName): bool
    {
        if ($this->isSqlite()) {
            return false;
        }

        try {
            $db = DB::connection()->getDatabaseName();

            return DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $fkName)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
};
