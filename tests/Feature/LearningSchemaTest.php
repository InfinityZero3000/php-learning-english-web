<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearningSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_tables_and_key_columns_exist(): void
    {
        foreach (['roles', 'levels', 'topics', 'courses', 'course_topic', 'lessons', 'vocabularies', 'quizzes', 'questions', 'answers', 'attempts', 'progress', 'bookmarks'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumn('users', 'role_id'));
        $this->assertTrue(Schema::hasColumns('courses', ['level_id', 'title', 'slug', 'description', 'status']));
        $this->assertTrue(Schema::hasColumns('attempts', ['user_id', 'quiz_id', 'score', 'started_at', 'completed_at']));
    }

    public function test_core_model_relationships_are_defined(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new User)->role());
        $this->assertInstanceOf(HasMany::class, (new Course)->lessons());
        $this->assertInstanceOf(BelongsTo::class, (new Lesson)->course());
        $this->assertInstanceOf(HasMany::class, (new Quiz)->questions());
    }
}
