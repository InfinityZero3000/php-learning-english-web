<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->string('status')->default('completed')->index()->after('quiz_id');
            $table->unsignedInteger('correct_answers')->nullable()->after('score');
            $table->unsignedInteger('total_questions')->nullable()->after('correct_answers');
            $table->unsignedBigInteger('active_quiz_id')->nullable()->after('total_questions');
            $table->unique(['user_id', 'active_quiz_id'], 'attempts_active_quiz_unique');
        });

        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('answer_id');
            $table->text('answer_content_snapshot');
            $table->boolean('is_correct');
            $table->text('explanation_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropUnique('attempts_active_quiz_unique');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'correct_answers', 'total_questions', 'active_quiz_id']);
        });
    }
};
