<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['content' => ['required', 'string'], 'is_correct' => ['required', 'boolean'], 'explanation' => ['nullable', 'string']];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $question = $this->route('question');
            if (! $question || $question->type === 'matching') {
                return;
            }
            $existingCorrect = $question->answers()->when($this->route('answer'), fn ($query, $answer) => $query->whereKeyNot($answer->id))->where('is_correct', true)->count();
            $isCorrect = $this->has('is_correct') ? $this->boolean('is_correct') : (bool) $this->route('answer')?->is_correct;
            if ($existingCorrect + (int) $isCorrect !== 1) {
                $validator->errors()->add('is_correct', 'A non-matching question must have exactly one correct answer.');
            }
        }];
    }
}
