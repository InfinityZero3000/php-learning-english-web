<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourseService
{
    private const CACHE_TTL = 3600;

    public function listPaginated(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $cacheKey = 'courses:list:' . md5(json_encode($filters) . $perPage);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $perPage) {
            $query = Course::with(['level', 'lessons', 'vocabularies'])
                ->withCount(['lessons', 'vocabularies']);

            if (!empty($filters['level_id'])) {
                $query->where('level_id', $filters['level_id']);
            }

            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        });
    }

    public function getById(int $id): Course
    {
        return Cache::remember("courses:detail:{$id}", self::CACHE_TTL, function () use ($id) {
            return Course::with([
                'level',
                'lessons' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'lessons.vocabularies',
                'lessons.quizzes',
                'vocabularies',
                'topics',
            ])->findOrFail($id);
        });
    }

    public function create(array $data): Course
    {
        try {
            $course = Course::create($data);
            $this->clearCache();
            Log::info('Course created', ['id' => $course->id, 'title' => $course->title]);
            return $course;
        } catch (\Exception $e) {
            Log::error('Failed to create course', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e;
        }
    }

    public function update(Course $course, array $data): Course
    {
        try {
            $course->update($data);
            Cache::forget("courses:detail:{$course->id}");
            $this->clearCache();
            Log::info('Course updated', ['id' => $course->id]);
            return $course;
        } catch (\Exception $e) {
            Log::error('Failed to update course', ['id' => $course->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function delete(Course $course): void
    {
        try {
            $course->delete();
            Cache::forget("courses:detail:{$course->id}");
            $this->clearCache();
            Log::info('Course deleted', ['id' => $course->id]);
        } catch (\Exception $e) {
            Log::error('Failed to delete course', ['id' => $course->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getStats(): array
    {
        return Cache::remember('courses:stats', self::CACHE_TTL, function () {
            return [
                'total'         => Course::count(),
                'published'      => Course::where('status', 'published')->count(),
                'total_lessons' => \App\Models\Lesson::count(),
                'total_vocabularies' => \App\Models\Vocabulary::count(),
            ];
        });
    }

    public function getAll(): Collection
    {
        return Cache::remember('courses:all', self::CACHE_TTL, function () {
            return Course::with(['level'])->orderBy('title')->get();
        });
    }

    private function clearCache(): void
    {
        Cache::forget('courses:stats');
        Cache::forget('courses:all');
        Cache::forget('dashboard:stats');
    }
}