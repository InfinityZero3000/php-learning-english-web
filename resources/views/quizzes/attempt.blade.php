@extends('layouts.app')
@section('title', 'Làm bài: ' . $quiz->title)

@section('content')
<div class="breadcrumb-linguist">
    <a href="#">Khóa học</a>
    <span class="sep">/</span>
    <a href="#">Bài học</a>
    <span class="sep">/</span>
    <span>Làm bài kiểm tra</span>
</div>

<div class="form-card" style="max-width: 800px; margin: 0 auto;">
    <h3 class="fw-bold mb-2">{{ $quiz->title }}</h3>
    <p class="text-muted">Điểm qua môn: {{ $quiz->passing_score }}%</p>
    
    <hr class="mb-4">

    @if($quiz->questions->isEmpty())
        <div class="alert alert-warning">Bài kiểm tra này chưa có câu hỏi nào.</div>
    @else
        <form action="{{ route('quizzes.submit', $quiz) }}" method="POST">
            @csrf
            
            @foreach($quiz->questions as $index => $question)
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-light fw-bold">
                        Câu {{ $index + 1 }}: {{ $question->content }}
                    </div>
                    <div class="card-body">
                        @foreach($question->answers as $answer)
                            <div class="form-check mb-2 custom-radio">
                                <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" id="ans_{{ $answer->id }}" value="{{ $answer->id }}" required>
                                <label class="form-check-label w-100 p-2 border rounded cursor-pointer option-label" for="ans_{{ $answer->id }}">
                                    {{ $answer->content }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="text-center mt-4">
                <button type="submit" class="btn-primary-linguist px-5 py-2 fs-5" onclick="return confirm('Bạn có chắc chắn nộp bài?')">
                    <i class="bi bi-send-check"></i> Nộp Bài
                </button>
            </div>
        </form>
    @endif
</div>

<style>
.custom-radio .form-check-input:checked + .option-label {
    background-color: #e9ecef;
    border-color: #0d6efd !important;
    font-weight: bold;
}
.cursor-pointer {
    cursor: pointer;
}
.option-label {
    transition: all 0.2s;
}
.option-label:hover {
    background-color: #f8f9fa;
}
</style>
@endsection

