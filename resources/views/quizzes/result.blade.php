@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow p-6 max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Kết quả: {{ $quiz->title }}</h1>

        <div class="bg-gray-50 rounded-lg p-6 mb-6 text-center">
            <p class="text-5xl font-bold {{ $attempt->score >= $quiz->passing_score ? 'text-green-600' : 'text-red-600' }}">{{ $attempt->score }}%</p>
            <p class="text-lg mt-2">
                @if($attempt->passed)
                    <span class="text-green-600 font-bold">Đạt</span>
                @else
                    <span class="text-red-600 font-bold">Chưa đạt (cần {{ $quiz->passing_score }}%)</span>
                @endif
            </p>
            <p class="text-sm text-gray-500 mt-2">Số câu đúng: {{ $attempt->correct_answers }}/{{ $attempt->total_questions }}</p>
            <p class="text-sm text-gray-500">Thời gian: {{ $attempt->created_at->format('d/m/Y H:i') }}</p>
        </div>

        <h2 class="text-xl font-bold mb-4">Chi tiết câu hỏi</h2>
        @php
            $userAnswers = $attempt->answers_data ?? [];
        @endphp
        @foreach($quiz->questions->sortBy('sort_order') as $question)
        @php
            $userAnswerId = $userAnswers[$question->id] ?? null;
            $correctAnswer = $question->answers->where('is_correct', true)->first();
            $selectedAnswer = $userAnswerId ? $question->answers->where('id', (int)$userAnswerId)->first() : null;
            $isCorrect = $selectedAnswer && $selectedAnswer->is_correct;
        @endphp
        <div class="mb-4 p-4 border rounded {{ $isCorrect ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }}">
            <p class="font-bold mb-2">{{ $question->sort_order }}. {{ $question->content }}</p>
            <div class="space-y-1">
                @foreach($question->answers as $answer)
                <p class="p-2 rounded
                    {{ $answer->is_correct ? 'bg-green-200 font-bold' : '' }}
                    {{ $selectedAnswer && $selectedAnswer->id == $answer->id && !$answer->is_correct ? 'bg-red-200' : '' }}">
                    {{ $answer->content }}
                    @if($answer->is_correct) &#10003; @endif
                    @if($selectedAnswer && $selectedAnswer->id == $answer->id && !$answer->is_correct) &#10007; @endif
                </p>
                @endforeach
            </div>
            @if($correctAnswer && $correctAnswer->explanation)
            <p class="text-sm text-gray-600 mt-2 italic">Giải thích: {{ $correctAnswer->explanation }}</p>
            @endif
        </div>
        @endforeach

        <div class="flex justify-between mt-6">
            <a href="{{ route('courses.show', $quiz->lesson->course) }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Về khóa học</a>
            <a href="{{ route('quizzes.show', $quiz) }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Làm lại</a>
        </div>
    </div>
</div>
@endsection