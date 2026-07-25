<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });
        Schema::create('vocabulary_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'vocabulary_id']);
        });
        Schema::create('study_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('activity_type', ['lesson', 'quiz', 'vocabulary']);
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_type');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_history');
        Schema::dropIfExists('vocabulary_bookmarks');
        Schema::dropIfExists('lesson_progress');
    }
};
