<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Quiz $quiz): AnonymousResourceCollection
    {
        return QuestionResource::collection($quiz->questions()->with('answers')->orderBy('order')->paginate());
    }

    public function store(StoreQuestionRequest $request, Quiz $quiz): QuestionResource
    {
        $question = DB::transaction(function () use ($request, $quiz) {
            $question = new Question;
            $question->forceFill(Arr::except($request->validated(), 'answers'));
            $quiz->questions()->save($question);
            foreach ($request->validated('answers') as $answerData) {
                $answer = new Answer;
                $answer->forceFill($answerData);
                $question->answers()->save($answer);
            }

            return $question;
        });

        return new QuestionResource($question->load('answers'));
    }

    public function show(Quiz $quiz, Question $question): QuestionResource
    {
        $this->ensureQuiz($quiz, $question);

        return new QuestionResource($question->load('answers'));
    }

    public function update(UpdateQuestionRequest $request, Quiz $quiz, Question $question): QuestionResource
    {
        $this->ensureQuiz($quiz, $question);
        DB::transaction(function () use ($request, $question) {
            $question->forceFill(Arr::except($request->validated(), 'answers'))->save();
            if ($request->has('answers')) {
                $question->answers()->delete();
                foreach ($request->validated('answers') as $answerData) {
                    $answer = new Answer;
                    $answer->forceFill($answerData);
                    $question->answers()->save($answer);
                }
            }
        });

        return new QuestionResource($question->fresh('answers'));
    }

    public function destroy(Quiz $quiz, Question $question): Response
    {
        $this->ensureQuiz($quiz, $question);
        $question->delete();

        return response()->noContent();
    }

    private function ensureQuiz(Quiz $quiz, Question $question): void
    {
        abort_unless($question->quiz_id === $quiz->id, 404);
    }
}
