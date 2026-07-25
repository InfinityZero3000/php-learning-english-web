<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserVocabulary;
use App\Models\Vocabulary;
use App\Models\VocabularyReview;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_tables_and_columns_exist(): void
    {
        foreach (['course_categories', 'units', 'user_vocabularies', 'vocabulary_reviews'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('courses', [
            'external_id', 'category_id', 'language', 'thumbnail_url',
            'estimated_duration', 'total_xp',
        ]));
        $this->assertTrue(Schema::hasColumns('lessons', [
            'external_id', 'unit_id', 'lesson_type', 'estimated_minutes',
            'xp_reward', 'pass_threshold',
        ]));
        $this->assertTrue(Schema::hasColumns('vocabularies', [
            'external_id', 'definition', 'translation', 'pronunciation',
            'part_of_speech', 'difficulty_level', 'tags', 'external_audio_url',
        ]));
    }

    public function test_model_relationships_and_json_casts_are_defined(): void
    {
        $this->assertInstanceOf(HasMany::class, (new CourseCategory)->courses());
        $this->assertInstanceOf(BelongsTo::class, (new Course)->category());
        $this->assertInstanceOf(HasMany::class, (new Course)->units());
        $this->assertInstanceOf(BelongsTo::class, (new Unit)->course());
        $this->assertInstanceOf(HasMany::class, (new Unit)->lessons());
        $this->assertInstanceOf(BelongsTo::class, (new Lesson)->unit());
        $this->assertInstanceOf(HasMany::class, (new User)->userVocabularies());
        $this->assertInstanceOf(HasMany::class, (new Vocabulary)->userVocabularies());
        $this->assertInstanceOf(HasMany::class, (new UserVocabulary)->reviews());

        $vocabulary = new Vocabulary(['translation' => ['vi' => 'xin chào'], 'tags' => ['greeting']]);
        $this->assertSame(['vi' => 'xin chào'], $vocabulary->translation);
        $this->assertSame(['greeting'], $vocabulary->tags);
    }

    public function test_user_vocabulary_is_unique_and_reviews_cascade(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['title' => 'Course', 'slug' => 'course', 'status' => 'draft']);
        $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Lesson', 'slug' => 'lesson']);
        $vocabulary = Vocabulary::create(['lesson_id' => $lesson->id, 'word' => 'hello', 'meaning' => 'xin chào']);
        $userVocabulary = UserVocabulary::create(['user_id' => $user->id, 'vocabulary_id' => $vocabulary->id]);
        VocabularyReview::create([
            'user_vocabulary_id' => $userVocabulary->id,
            'rating' => 3,
            'stability' => 1,
            'difficulty' => 5,
            'scheduled_days' => 1,
            'elapsed_days' => 0,
            'reviewed_at' => now(),
        ]);

        try {
            UserVocabulary::create(['user_id' => $user->id, 'vocabulary_id' => $vocabulary->id]);
            $this->fail('Duplicate user vocabulary was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('user_vocabularies', 1);
        }

        $userVocabulary->delete();
        $this->assertDatabaseCount('vocabulary_reviews', 0);
    }

    public function test_legacy_backfill_preserves_lesson_data(): void
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Legacy Course',
            'slug' => 'legacy-course',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $lessonId = DB::table('lessons')->insertGetId([
            'course_id' => $courseId,
            'title' => 'Old Lesson',
            'slug' => 'old-lesson',
            'content' => 'Plain legacy content',
            'sort_order' => 0,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unitId = DB::table('units')->insertGetId([
            'course_id' => $courseId,
            'title' => 'Legacy',
            'sort_order' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lessons')->where('id', $lessonId)->update(['unit_id' => $unitId]);

        $this->assertDatabaseHas('lessons', [
            'id' => $lessonId,
            'course_id' => $courseId,
            'unit_id' => $unitId,
            'slug' => 'old-lesson',
            'content' => 'Plain legacy content',
        ]);
    }
}
