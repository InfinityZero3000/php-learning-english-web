<?php

namespace App\Http\Controllers\Api;

use App\Events\StudyActivityOccurred;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLessonProgressRequest;
use App\Http\Resources\ProgressResource;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function update(UpdateLessonProgressRequest $request, Lesson $lesson): ProgressResource
    {
        $status = $request->validated('status');
        $percent = match ($status) {
            'not_started' => 0, 'in_progress' => 50, 'completed' => 100
        };
        DB::table('lesson_progress')->upsert(['user_id' => $request->user()->id, 'lesson_id' => $lesson->id, 'status' => $status, 'progress_percent' => $percent, 'completed_at' => $status === 'completed' ? now() : null, 'created_at' => now(), 'updated_at' => now()], ['user_id', 'lesson_id'], ['status', 'progress_percent', 'completed_at', 'updated_at']);
        if ($status === 'completed') {
            event(new StudyActivityOccurred($request->user()->id, 'lesson', $lesson->id, Lesson::class, $request->validated('duration_seconds', 0)));
        }

return new ProgressResource(DB::table('lesson_progress')->where('user_id', $request->user()->id)->where('lesson_id', $lesson->id)->first());
    }
}
