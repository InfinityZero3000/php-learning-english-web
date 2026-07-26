<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Level;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LessonQuizSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private int $course1Id;

    private int $course2Id;

    private int $lesson1Id;

    private int $beginnerLevelId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
        $this->seed(LessonQuizSeeder::class);

        $this->course1Id = Course::where('slug', 'tieng-anh-co-ban')->value('id');
        $this->course2Id = Course::where('slug', 'tieng-anh-giao-tiep')->value('id');
        $this->lesson1Id = Lesson::where('slug', 'dong-vat-hoang-da')->value('id');
        $this->beginnerLevelId = Level::where('slug', 'beginner')->value('id');
    }

    public function test_courses_listing_returns_paginated_published_courses(): void
    {
        $this->getJson('/api/v1/catalog/courses')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_courses_can_be_searched(): void
    {
        $this->getJson('/api/v1/catalog/courses?search=Cơ+Bản')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'tieng-anh-co-ban');
    }

    public function test_courses_can_be_filtered_by_level(): void
    {
        $this->getJson("/api/v1/catalog/courses?level_id={$this->beginnerLevelId}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'tieng-anh-co-ban');
    }

    public function test_course_detail_returns_course_data(): void
    {
        $this->getJson("/api/v1/catalog/courses/{$this->course1Id}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'tieng-anh-co-ban')
            ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'lessons_count']]);
    }

    public function test_lessons_listing_returns_published_lessons(): void
    {
        $this->getJson('/api/v1/catalog/lessons')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_draft_lessons_are_not_listed(): void
    {
        $this->getJson('/api/v1/catalog/lessons')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $response = $this->getJson('/api/v1/catalog/lessons');
        $slugs = collect($response->json('data'))->pluck('slug')->toArray();
        $this->assertNotContains('mau-sac', $slugs);
    }

    public function test_course_lessons_returns_paginated_result(): void
    {
        $this->getJson("/api/v1/catalog/courses/{$this->course1Id}/lessons")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_lesson_detail_returns_lesson_with_content(): void
    {
        $this->getJson("/api/v1/catalog/lessons/{$this->lesson1Id}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'dong-vat-hoang-da')
            ->assertJsonStructure(['data' => ['quizzes_count', 'vocabularies_count']]);
    }

    public function test_pagination_respects_per_page(): void
    {
        $this->getJson('/api/v1/catalog/lessons?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data');
    }
}

