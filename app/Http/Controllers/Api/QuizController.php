<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Quiz;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return QuizResource::collection(Quiz::query()->withCount('questions')->paginate());
    }

    public function store(StoreQuizRequest $request): QuizResource
    {
        $quiz = DB::transaction(function () use ($request) {
            $quiz = new Quiz;
            $quiz->forceFill(Arr::except($request->validated(), 'questions'))->save();
            $this->syncQuestions($quiz, $request->validated('questions', []));

            return $quiz;
        });

        return new QuizResource($quiz->load('questions.answers'));
    }

    public function show(Quiz $quiz): QuizResource
    {
        return new QuizResource($quiz->load('questions.answers'));
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz): QuizResource
    {
        DB::transaction(function () use ($request, $quiz) {
            $quiz->forceFill(Arr::except($request->validated(), 'questions'))->save();
            if ($request->has('questions')) {
                $this->syncQuestions($quiz, $request->validated('questions'));
            }
        });

        return new QuizResource($quiz->fresh('questions.answers'));
    }

    public function destroy(Quiz $quiz): Response
    {
        $quiz->delete();

        return response()->noContent();
    }

    private function syncQuestions(Quiz $quiz, array $questions): void
    {
        foreach ($questions as $data) {
            $question = isset($data['id']) ? $quiz->questions()->findOrFail($data['id']) : $quiz->questions()->make();
            $question->forceFill(Arr::except($data, ['id', 'answers']));
            $question->save();
            foreach ($data['answers'] as $answer) {
                $answerId = $answer['id'] ?? null;
                $answerModel = $answerId ? $question->answers()->findOrFail($answerId) : $question->answers()->make();
                $answerModel->forceFill(Arr::except($answer, 'id'));
                $answerModel->save();
            }
        }
    }
}
