<?php

namespace Tests\Feature\Api\V1;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\LearningSession;
use App\Models\Lesson;
use App\Models\User;
use App\Services\LearningSessionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Throwable;

#[Group('mysql-concurrency')]
class CourseLearningPathMysqlConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_request_ids_create_one_active_enrollment_lesson_session(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('The mysql-concurrency group requires MySQL row locks.');
        }

        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'Concurrency course',
            'slug' => 'concurrency-'.Str::random(8),
            'status' => 'published',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson',
            'slug' => 'lesson-'.Str::random(8),
            'status' => 'published',
            'sort_order' => 0,
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
        $requestIds = [(string) Str::uuid(), (string) Str::uuid()];
        $children = [];

        foreach ($requestIds as $requestId) {
            [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            if ($pid === 0) {
                fclose($parent);
                fread($child, 1);
                try {
                    DB::purge();
                    $session = app(LearningSessionService::class)->start(
                        User::findOrFail($user->id),
                        ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id],
                        $requestId,
                    );
                    fwrite($child, (string) $session->id);
                } catch (Throwable $exception) {
                    fwrite($child, 'ERROR:'.$exception->getMessage());
                }
                fclose($child);
                exit(0);
            }
            fclose($child);
            $children[] = [$pid, $parent];
        }

        foreach ($children as [, $socket]) {
            fwrite($socket, '1');
        }
        $results = [];
        foreach ($children as [$pid, $socket]) {
            $results[] = stream_get_contents($socket);
            fclose($socket);
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        $this->assertSame($results[0], $results[1], implode(' | ', $results));
        $this->assertSame(1, LearningSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->where('status', 'active')
            ->count());
        $this->assertSame(2, LearningEvent::query()->whereIn('request_id', $requestIds)->count());
    }
}
