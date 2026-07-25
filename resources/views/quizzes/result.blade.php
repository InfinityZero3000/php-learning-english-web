@extends('layouts.app')
@section('title', 'Kết quả: ' . $quiz->title)

@section('content')
<div class="breadcrumb-linguist">
    <a href="#">Khóa học</a>
    <span class="sep">/</span>
    <a href="#">Bài học</a>
    <span class="sep">/</span>
    <span>Kết quả kiểm tra</span>
</div>

<div class="form-card" style="max-width: 800px; margin: 0 auto;">
    <div class="text-center mb-5">
        <h2 class="fw-bold mb-3">Kết quả: {{ $quiz->title }}</h2>
        
        <div class="display-1 fw-bold {{ $attempt->score >= $quiz->passing_score ? 'text-success' : 'text-danger' }}">
            {{ $attempt->score }}%
        </div>
        
        <div class="mt-3 fs-5">
            @if($attempt->score >= $quiz->passing_score)
                <span class="badge bg-success p-2 fs-6"><i class="bi bi-check-circle"></i> CHÚC MỪNG BẠN ĐÃ VƯỢT QUA</span>
            @else
                <span class="badge bg-danger p-2 fs-6"><i class="bi bi-x-circle"></i> CHƯA ĐẠT, VUI LÒNG THỬ LẠI</span>
            @endif
        </div>
        <p class="text-muted mt-2">Điểm yêu cầu: {{ $quiz->passing_score }}%</p>
    </div>

    <h4 class="fw-bold mb-3 border-bottom pb-2">Chi tiết đáp án</h4>
    
    @foreach($quiz->questions as $index => $question)
        @php
            $correctAnswers = $question->answers->where('is_correct', true)->pluck('id')->toArray();
            // Since we didn't save individual answers for the user in the database (only total score), 
            // we will just show the correct answers and explanation here.
        @endphp
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-light fw-bold">
                Câu {{ $index + 1 }}: {{ $question->content }}
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush mb-3">
                    @foreach($question->answers as $answer)
                        <li class="list-group-item {{ $answer->is_correct ? 'list-group-item-success fw-bold' : '' }}">
                            @if($answer->is_correct)
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                            @else
                                <i class="bi bi-circle text-muted me-2"></i>
                            @endif
                            {{ $answer->content }}
                        </li>
                    @endforeach
                </ul>
                
                @if($question->explanation)
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle-fill me-2"></i> <strong>Giải thích:</strong> {{ $question->explanation }}
                    </div>
                @endif
            </div>
        </div>
    @endforeach
    
    <div class="text-center mt-4">
        <a href="#" class="btn-outline-linguist me-2"><i class="bi bi-arrow-left"></i> Quay lại bài học</a>
        <a href="{{ route('quizzes.attempt', $quiz) }}" class="btn-primary-linguist"><i class="bi bi-arrow-clockwise"></i> Làm lại</a>
    </div>
</div>
@endsection

