<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update vocabularies table
        Schema::table('vocabularies', function (Blueprint $table): void {
            if (!Schema::hasColumn('vocabularies', 'translation')) {
                $table->text('translation')->nullable()->after('word');
            }
            if (!Schema::hasColumn('vocabularies', 'pronunciation')) {
                $table->string('pronunciation')->nullable()->after('translation');
            }
            if (!Schema::hasColumn('vocabularies', 'category')) {
                $table->string('category')->nullable()->after('pronunciation');
            }
            if (!Schema::hasColumn('vocabularies', 'difficulty')) {
                $table->enum('difficulty', ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'])->nullable()->after('category');
            }
            if (!Schema::hasColumn('vocabularies', 'cefr_level')) {
                $table->enum('cefr_level', ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])->nullable()->after('difficulty');
            }
            if (!Schema::hasColumn('vocabularies', 'part_of_speech')) {
                $table->string('part_of_speech')->nullable()->after('cefr_level');
            }
            if (!Schema::hasColumn('vocabularies', 'image_url')) {
                $table->string('image_url')->nullable()->after('part_of_speech');
            }
            if (!Schema::hasColumn('vocabularies', 'audio_url')) {
                $table->string('audio_url')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('vocabularies', 'enrichment_status')) {
                $table->string('enrichment_status')->default('none')->after('audio_url');
            }
            if (!Schema::hasColumn('vocabularies', 'synonyms')) {
                $table->text('synonyms')->nullable()->after('enrichment_status');
            }
            if (!Schema::hasColumn('vocabularies', 'antonyms')) {
                $table->text('antonyms')->nullable()->after('synonyms');
            }
        });

        // Update topics table
        Schema::table('topics', function (Blueprint $table): void {
            if (!Schema::hasColumn('topics', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('topics', 'icon_emoji')) {
                $table->string('icon_emoji')->nullable()->after('description');
            }
        });

        // Vocabulary Sets
        Schema::create('vocabulary_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('vocabulary_set_word', function (Blueprint $table): void {
            $table->foreignId('vocabulary_set_id')->constrained('vocabulary_sets')->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->primary(['vocabulary_set_id', 'vocabulary_id']);
        });

        // User Word Progress (FSRS)
        Schema::create('user_word_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->string('mastery')->default('new'); // new, learning, review, mastered
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->float('stability')->default(0);
            $table->float('difficulty')->default(0);
            $table->unsignedInteger('repetitions')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            $table->unsignedInteger('state')->default(0); // FSRS state
            $table->timestamp('due')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'vocabulary_id']);
            $table->index(['user_id', 'due']);
        });

        // Quiz Sessions
        Schema::create('quiz_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->boolean('completed')->default(false);
            $table->decimal('score', 5, 2)->nullable();
            $table->string('type')->default('vocabulary');
            $table->string('category')->nullable();
            $table->string('difficulty')->nullable();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cefr_level')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_session_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->boolean('correct')->default(false);
            $table->unsignedInteger('response_ms')->default(0);
            $table->timestamps();
        });

        // Streaks
        Schema::create('user_streaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->date('last_check_in_date')->nullable();
            $table->unsignedInteger('total_check_ins')->default(0);
            $table->timestamps();
            $table->unique(['user_id']);
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->enum('type', [
                'REVIEW_REMINDER', 'DAILY_DUE_REMINDER', 'EVENING_REVIEW_REMINDER',
                'OVERDUE_REMINDER', 'STREAK_ALERT', 'ACHIEVEMENT', 'SYSTEM'
            ])->default('SYSTEM');
            $table->string('deep_link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('read')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_read']);
        });

        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('review_reminders_enabled')->default(true);
            $table->string('preferred_reminder_time')->default('09:00');
            $table->boolean('evening_reminder_enabled')->default(true);
            $table->string('evening_reminder_time')->default('20:00');
            $table->boolean('comeback_reminder_enabled')->default(true);
            $table->unsignedInteger('comeback_reminder_interval_days')->default(3);
            $table->unsignedInteger('evening_remaining_threshold')->default(5);
            $table->timestamps();
            $table->unique(['user_id']);
        });

        // Import Jobs
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type')->nullable(); // csv, json, xml
            $table->string('file_name')->nullable();
            $table->string('target_set_name')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('rows')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });

        // Flashcards
        Schema::create('flashcard_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('flashcards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flashcard_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('word')->index();
            $table->text('translation')->nullable();
            $table->text('definition')->nullable();
            $table->text('example')->nullable();
            $table->string('level')->nullable();
            $table->string('source')->nullable();
            $table->string('source_name')->nullable();
            $table->string('topic')->nullable();
            $table->string('front')->nullable();
            $table->string('back')->nullable();
            $table->boolean('is_imported')->default(false);
            $table->timestamps();
        });

        // Word of the Day
        Schema::create('word_of_the_day', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->date('date')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_of_the_day');
        Schema::dropIfExists('flashcards');
        Schema::dropIfExists('flashcard_sources');
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('user_streaks');
        Schema::dropIfExists('quiz_session_answers');
        Schema::dropIfExists('quiz_sessions');
        Schema::dropIfExists('user_word_progress');
        Schema::dropIfExists('vocabulary_set_word');
        Schema::dropIfExists('vocabulary_sets');

        Schema::table('topics', function (Blueprint $table): void {
            $table->dropColumn(['icon_emoji', 'description']);
        });

        Schema::table('vocabularies', function (Blueprint $table): void {
            $columns = ['antonyms', 'synonyms', 'enrichment_status', 'audio_url', 'image_url',
                'part_of_speech', 'cefr_level', 'difficulty', 'category', 'pronunciation', 'translation'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('vocabularies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};