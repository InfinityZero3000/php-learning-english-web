<?php

namespace App\Http\Controllers\Api;

use App\Events\StudyActivityOccurred;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttemptAnswerRequest;
use App\Http\Resources\AttemptResource;
use App\Http\Resources\QuestionResource;
use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AttemptController extends Controller
{
    public function start(Quiz $quiz): JsonResponse
    {
        $attempt = new Attempt;
        $attempt->forceFill(['user_id' => request()->user()->id, 'quiz_id' => $quiz->id, 'score' => 0, 'total_questions' => $quiz->questions()->count(), 'correct_answers' => 0, 'started_at' => now(), 'status' => 'in_progress'])->save();
        $questions = $quiz->questions()->with(['answers' => fn ($query) => $query->select('id', 'question_id', 'content')])->orderBy('order')->get();

        return response()->json(['attempt' => new AttemptResource($attempt), 'questions' => QuestionResource::collection($questions)], 201);
    }

    public function answer(StoreAttemptAnswerRequest $request, Attempt $attempt): JsonResponse
    {
        $this->authorize('update', $attempt);
        abort_if($attempt->status !== 'in_progress', 422, 'This attempt is already completed.');
        $data = $request->validated();
        $question = $attempt->quiz->questions()->with('answers')->findOrFail($data['question_id']);
        $answerIds = $data['answer_ids'] ?? (isset($data['answer_id']) ? [$data['answer_id']] : []);
        $validIds = $question->answers->pluck('id');
        abort_unless(collect($answerIds)->every(fn ($id) => $validIds->contains($id)), 422, 'Selected answer does not belong to the question.');
        $correctIds = $question->answers->where('is_correct', true)->pluck('id')->sort()->values();
        $selectedIds = collect($answerIds)->unique()->sort()->values();
        $isCorrect = $question->type === 'matching' ? $selectedIds->all() === $correctIds->all() : $selectedIds->count() === 1 && $correctIds->contains($selectedIds->first());
        DB::transaction(function () use ($attempt, $question, $selectedIds, $isCorrect): void {
            DB::table('attempt_answers')->where('attempt_id', $attempt->id)->where('question_id', $question->id)->delete();
            foreach ($selectedIds as $answerId) {
                DB::table('attempt_answers')->insert(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'answer_id' => $answerId, 'is_correct' => $isCorrect, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['question_id' => $question->id, 'is_correct' => $isCorrect]);
    }

    public function finish(Attempt $attempt): AttemptResource
    {
        $this->authorize('finish', $attempt);
        abort_if($attempt->status !== 'in_progress', 422, 'This attempt is already completed.');
        DB::transaction(function () use ($attempt): void {
            $attempt->refresh();
            $questions = $attempt->quiz->questions()->with('answers')->get();
            $correct = 0;
            foreach ($questions as $question) {
                $selected = DB::table('attempt_answers')->where('attempt_id', $attempt->id)->where('question_id', $question->id)->pluck('answer_id')->sort()->values();
                $expected = $question->answers->where('is_correct', true)->pluck('id')->sort()->values();
                $isCorrect = $question->type === 'matching' ? $selected->all() === $expected->all() : $selected->count() === 1 && $expected->contains($selected->first());
                $correct += $isCorrect ? 1 : 0;
            }
            $total = $questions->count();
            $attempt->forceFill(['total_questions' => $total, 'correct_answers' => $correct, 'score' => $total ? round($correct / $total * 100, 2) : 0, 'status' => 'completed', 'finished_at' => now()])->save();
            event(new StudyActivityOccurred($attempt->user_id, 'quiz', $attempt->quiz_id, Quiz::class));
        });

        return new AttemptResource($attempt->refresh());
    }

    public function result(Attempt $attempt): JsonResponse
    {
        $this->authorize('view', $attempt);
        $attempt->load('quiz.questions.answers');
        $answers = DB::table('attempt_answers')->leftJoin('answers', 'answers.id', '=', 'attempt_answers.answer_id')->where('attempt_answers.attempt_id', $attempt->id)->select('attempt_answers.question_id', 'attempt_answers.answer_id', 'attempt_answers.is_correct', 'answers.content')->get()->groupBy('question_id');
        $questions = $attempt->quiz->questions->map(fn ($question) => ['id' => $question->id, 'content' => $question->content, 'type' => $question->type, 'selected_answers' => $answers->get($question->id, collect())->map(fn ($item) => ['id' => $item->answer_id, 'content' => $item->content, 'is_correct' => (bool) $item->is_correct])->values(), 'correct_answers' => $question->answers->where('is_correct', true)->map(fn ($answer) => ['id' => $answer->id, 'content' => $answer->content])->values(), 'explanation' => $question->answers->where('is_correct', true)->pluck('explanation')->filter()->values()]);

        return response()->json(['attempt' => new AttemptResource($attempt), 'questions' => $questions]);
    }
}
