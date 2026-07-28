<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress', function (Blueprint $table) {
            $table->string('status')->default('not_started');
            $table->unsignedTinyInteger('best_score')->nullable();
            $table->timestamp('started_at')->nullable();
        });

        Schema::table('user_vocabularies', function (Blueprint $table) {
            $table->string('state')->default('learning')->change();
            $table->double('stability')->nullable()->default(null)->change();
            $table->double('difficulty')->nullable()->default(null)->change();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->string('algorithm')->nullable();
            $table->string('algorithm_version')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
        });

        Schema::table('vocabulary_reviews', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->unique();
            $table->string('algorithm')->nullable();
            $table->string('algorithm_version')->nullable();
            $table->unsignedBigInteger('base_revision')->nullable();
            $table->unsignedBigInteger('resulting_revision')->nullable();
            $table->string('before_state')->nullable();
            $table->string('after_state')->nullable();
            $table->timestamp('before_due_at')->nullable();
            $table->timestamp('after_due_at')->nullable();
            $table->double('before_stability')->nullable();
            $table->double('after_stability')->nullable();
            $table->double('before_difficulty')->nullable();
            $table->double('after_difficulty')->nullable();
            $table->unsignedInteger('before_scheduled_days')->nullable();
            $table->unsignedInteger('after_scheduled_days')->nullable();
            $table->timestamp('before_last_reviewed_at')->nullable();
            $table->timestamp('after_last_reviewed_at')->nullable();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained();
            $table->string('status')->default('active');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('learner_id')->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'learner_id']);
        });

        Schema::create('learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('learning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vocabulary_review_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->uuid('request_id')->nullable()->unique();
            $table->text('response')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('hint_level')->nullable();
            $table->decimal('pronunciation_score', 5, 2)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('learning_assistance_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_event_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('source');
            $table->string('status');
            $table->string('schema_version');
            $table->json('diagnosis')->nullable();
            $table->text('feedback')->nullable();
            $table->json('hints')->nullable();
            $table->string('degraded_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key');
            $table->unsignedInteger('version');
            $table->boolean('enabled')->default(true);
            $table->json('parameters');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['rule_key', 'version']);
        });

        Schema::create('supervision_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('users');
            $table->foreignId('alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_key');
            $table->unsignedInteger('rule_version');
            $table->string('fingerprint')->unique();
            $table->string('severity');
            $table->json('evidence');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state')->default('open');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['learner_id', 'state']);
        });

        $createAssignments = function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supervision_alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('learner_id')->constrained('users');
            $table->foreignId('lesson_id')->nullable()->constrained();
            $table->foreignId('vocabulary_id')->nullable()->constrained();
            $table->string('status')->default('pending');
            $table->string('target_type')->storedAs(
                "CASE WHEN lesson_id IS NOT NULL AND vocabulary_id IS NULL THEN 'lesson' ".
                "WHEN lesson_id IS NULL AND vocabulary_id IS NOT NULL THEN 'vocabulary' END"
            );
            $table->unsignedBigInteger('active_lesson_id')->nullable()->storedAs(
                "CASE WHEN status IN ('pending', 'in_progress') THEN lesson_id END"
            );
            $table->unsignedBigInteger('active_vocabulary_id')->nullable()->storedAs(
                "CASE WHEN status IN ('pending', 'in_progress') THEN vocabulary_id END"
            );
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['teacher_id', 'learner_id', 'active_lesson_id'],
                'assignments_active_lesson_unique'
            );
            $table->unique(
                ['teacher_id', 'learner_id', 'active_vocabulary_id'],
                'assignments_active_vocabulary_unique'
            );
        };

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE assignments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    supervision_alert_id INTEGER,
                    teacher_id INTEGER NOT NULL,
                    learner_id INTEGER NOT NULL,
                    lesson_id INTEGER,
                    vocabulary_id INTEGER,
                    status VARCHAR NOT NULL DEFAULT 'pending',
                    target_type VARCHAR GENERATED ALWAYS AS (
                        CASE
                            WHEN lesson_id IS NOT NULL AND vocabulary_id IS NULL THEN 'lesson'
                            WHEN lesson_id IS NULL AND vocabulary_id IS NOT NULL THEN 'vocabulary'
                        END
                    ) STORED,
                    active_lesson_id INTEGER GENERATED ALWAYS AS (
                        CASE WHEN status IN ('pending', 'in_progress') THEN lesson_id END
                    ) STORED,
                    active_vocabulary_id INTEGER GENERATED ALWAYS AS (
                        CASE WHEN status IN ('pending', 'in_progress') THEN vocabulary_id END
                    ) STORED,
                    instructions TEXT,
                    due_at DATETIME,
                    completed_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    CONSTRAINT assignments_target_xor CHECK (
                        (lesson_id IS NOT NULL AND vocabulary_id IS NULL)
                        OR (lesson_id IS NULL AND vocabulary_id IS NOT NULL)
                    ),
                    FOREIGN KEY (supervision_alert_id) REFERENCES supervision_alerts(id) ON DELETE SET NULL,
                    FOREIGN KEY (teacher_id) REFERENCES users(id),
                    FOREIGN KEY (learner_id) REFERENCES users(id),
                    FOREIGN KEY (lesson_id) REFERENCES lessons(id),
                    FOREIGN KEY (vocabulary_id) REFERENCES vocabularies(id)
                )
                SQL);
            Schema::table('assignments', function (Blueprint $table) {
                $table->unique(
                    ['teacher_id', 'learner_id', 'active_lesson_id'],
                    'assignments_active_lesson_unique'
                );
                $table->unique(
                    ['teacher_id', 'learner_id', 'active_vocabulary_id'],
                    'assignments_active_vocabulary_unique'
                );
            });
        } else {
            Schema::create('assignments', $createAssignments);
            DB::statement(
                'ALTER TABLE assignments ADD CONSTRAINT assignments_target_xor CHECK ('.
                '(lesson_id IS NOT NULL AND vocabulary_id IS NULL) OR '.
                '(lesson_id IS NULL AND vocabulary_id IS NOT NULL))'
            );
        }

        Schema::create('intervention_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervision_alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('learner_id')->constrained('users');
            $table->text('note');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('operations_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->uuid('request_id')->nullable()->unique();
            $table->json('context')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'occurred_at']);
        });

        Schema::create('quota_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->json('limits');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_policies');
        Schema::dropIfExists('operations_audits');
        Schema::dropIfExists('intervention_notes');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('supervision_alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('learning_assistance_results');
        Schema::dropIfExists('learning_events');
        Schema::dropIfExists('learning_sessions');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('enrollments');

        Schema::table('vocabulary_reviews', function (Blueprint $table) {
            $table->dropUnique(['request_id']);
            $table->dropColumn([
                'request_id', 'algorithm', 'algorithm_version', 'base_revision',
                'resulting_revision', 'before_state', 'after_state',
                'before_due_at', 'after_due_at', 'before_stability',
                'after_stability', 'before_difficulty', 'after_difficulty',
                'before_scheduled_days', 'after_scheduled_days',
                'before_last_reviewed_at', 'after_last_reviewed_at',
            ]);
        });

        DB::table('user_vocabularies')->whereNull('stability')->update(['stability' => 0]);
        DB::table('user_vocabularies')->whereNull('difficulty')->update(['difficulty' => 0]);
        Schema::table('user_vocabularies', function (Blueprint $table) {
            $table->dropColumn(['last_reviewed_at', 'algorithm', 'algorithm_version', 'revision']);
            $table->string('state')->default('new')->change();
            $table->double('stability')->nullable(false)->default(0)->change();
            $table->double('difficulty')->nullable(false)->default(0)->change();
        });

        Schema::table('progress', function (Blueprint $table) {
            $table->dropColumn(['status', 'best_score', 'started_at']);
        });
    }
};
