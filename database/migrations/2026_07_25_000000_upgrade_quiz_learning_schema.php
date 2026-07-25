<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->change();
            $table->text('description')->nullable()->after('title');
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner')->after('description');
            $table->enum('cefr', ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])->default('A1')->after('level');
            $table->foreignId('topic_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('order')->default(0)->after('cefr');
            $table->boolean('is_published')->default(false)->after('order');
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->nullable()->change();
            $table->text('description')->nullable()->after('title');
            $table->unsignedInteger('time_limit')->nullable()->after('description');
            $table->unsignedTinyInteger('pass_score')->default(60)->after('time_limit');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->enum('type', ['multiple_choice', 'fill_blank', 'listening', 'matching'])->default('multiple_choice')->after('content');
            $table->string('image_url')->nullable()->after('type');
            $table->string('audio_url')->nullable()->after('image_url');
            $table->unsignedInteger('order')->default(0)->after('audio_url');
        });

        Schema::table('answers', function (Blueprint $table): void {
            $table->text('explanation')->nullable()->after('is_correct');
        });

        Schema::table('attempts', function (Blueprint $table): void {
            $table->decimal('score', 5, 2)->unsigned()->default(0)->change();
            $table->unsignedInteger('total_questions')->default(0)->after('score');
            $table->unsignedInteger('correct_answers')->default(0)->after('total_questions');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress')->after('finished_at');
        });

        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id', 'answer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropColumn(['total_questions', 'correct_answers', 'finished_at', 'status']);
        });
        Schema::table('answers', fn (Blueprint $table) => $table->dropColumn('explanation'));
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn(['type', 'image_url', 'audio_url', 'order']));
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn(['description', 'time_limit', 'pass_score']));
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('topic_id');
            $table->dropColumn(['description', 'level', 'cefr', 'order', 'is_published']);
        });
    }
};
