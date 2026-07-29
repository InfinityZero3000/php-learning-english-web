<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocabulary_quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('quiz_type', 10);
            $table->json('word_ids');
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('incorrect_count')->default(0);
            $table->string('status')->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('learner_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20);
            $table->string('target_set_name')->nullable();
            $table->unsignedSmallInteger('total_rows');
            $table->unsignedSmallInteger('created_count')->default(0);
            $table->unsignedSmallInteger('skipped_count')->default(0);
            $table->string('status')->default('completed');
            $table->timestamps();
        });

        Schema::create('learner_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_key');
            $table->timestamp('read_at');
            $table->unique(['user_id', 'notification_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_notification_reads');
        Schema::dropIfExists('learner_import_runs');
        Schema::dropIfExists('vocabulary_quiz_sessions');
    }
};
