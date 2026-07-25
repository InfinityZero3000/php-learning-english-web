<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('level_id')->constrained('course_categories')->nullOnDelete();
            $table->string('external_id')->nullable()->unique();
            $table->string('language')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('estimated_duration')->nullable();
            $table->unsignedInteger('total_xp')->default(0);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('icon_url')->nullable();
            $table->string('background_color')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'sort_order']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->unique();
            $table->string('lesson_type')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedTinyInteger('pass_threshold')->nullable();
        });

        DB::table('courses')->orderBy('id')->each(function (object $course): void {
            $unitId = DB::table('units')->insertGetId([
                'course_id' => $course->id,
                'title' => 'Legacy',
                'sort_order' => -1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('lessons')->where('course_id', $course->id)->update(['unit_id' => $unitId]);
        });

        Schema::table('vocabularies', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique();
            $table->text('definition')->nullable();
            $table->json('translation')->nullable();
            $table->string('pronunciation')->nullable();
            $table->string('part_of_speech')->nullable();
            $table->string('difficulty_level')->nullable();
            $table->json('tags')->nullable();
            $table->string('external_audio_url')->nullable();
        });

        Schema::create('user_vocabularies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('state')->default('new');
            $table->double('stability')->default(0);
            $table->double('difficulty')->default(0);
            $table->unsignedInteger('scheduled_days')->default(0);
            $table->unsignedInteger('elapsed_days')->default(0);
            $table->unsignedInteger('repetitions')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'vocabulary_id']);
            $table->index(['user_id', 'due_at']);
        });

        Schema::create('vocabulary_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_vocabulary_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->double('stability');
            $table->double('difficulty');
            $table->unsignedInteger('scheduled_days');
            $table->unsignedInteger('elapsed_days');
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->index(['user_vocabulary_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocabulary_reviews');
        Schema::dropIfExists('user_vocabularies');

        Schema::table('vocabularies', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn([
                'external_id', 'definition', 'translation', 'pronunciation',
                'part_of_speech', 'difficulty_level', 'tags', 'external_audio_url',
            ]);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropUnique(['external_id']);
            $table->dropColumn([
                'external_id', 'lesson_type', 'estimated_minutes',
                'xp_reward', 'pass_threshold',
            ]);
        });

        Schema::dropIfExists('units');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropUnique(['external_id']);
            $table->dropColumn([
                'external_id', 'language', 'thumbnail_url',
                'estimated_duration', 'total_xp',
            ]);
        });

        Schema::dropIfExists('course_categories');
    }
};
