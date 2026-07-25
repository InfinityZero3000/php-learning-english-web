<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnswerRequest;
use App\Http\Requests\UpdateAnswerRequest;
use App\Http\Resources\AnswerResource;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AnswerController extends Controller
{
    public function index(Quiz $quiz, Question $question): AnonymousResourceCollection
    {
        $this->ensureQuestion($quiz, $question);

        return AnswerResource::collection($question->answers);
    }

    public function store(StoreAnswerRequest $request, Quiz $quiz, Question $question): AnswerResource
    {
        $this->ensureQuestion($quiz, $question);

        $answer = new Answer;
        $answer->forceFill($request->validated());
        $question->answers()->save($answer);

        return new AnswerResource($answer);
    }

    public function show(Quiz $quiz, Question $question, Answer $answer): AnswerResource
    {
        $this->ensureAnswer($quiz, $question, $answer);

        return new AnswerResource($answer);
    }

    public function update(UpdateAnswerRequest $request, Quiz $quiz, Question $question, Answer $answer): AnswerResource
    {
        $this->ensureAnswer($quiz, $question, $answer);
        $answer->forceFill($request->validated())->save();

        return new AnswerResource($answer);
    }

    public function destroy(Quiz $quiz, Question $question, Answer $answer): Response
    {
        $this->ensureAnswer($quiz, $question, $answer);
        abort_if($question->type !== 'matching' && $answer->is_correct && $question->answers()->where('is_correct', true)->count() === 1, 422, 'A non-matching question must retain one correct answer.');
        $answer->delete();

        return response()->noContent();
    }

    private function ensureQuestion(Quiz $quiz, Question $question): void
    {
        abort_unless($question->quiz_id === $quiz->id, 404);
    }

    private function ensureAnswer(Quiz $quiz, Question $question, Answer $answer): void
    {
        $this->ensureQuestion($quiz, $question);
        abort_unless($answer->question_id === $question->id, 404);
    }
}
