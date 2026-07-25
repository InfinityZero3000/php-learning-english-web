@extends('layouts.app')
@section('title','Câu hỏi quiz')
@section('top','Quản lý nội dung')
@section('subtitle','Soạn câu hỏi, nhập từ file và quản lý kết quả')

@section('content')
<div class="hero">
    <h1>{{ $quiz->title }}</h1>
    <p>Soạn câu hỏi, nhập từ CSV/Word và theo dõi kết quả học sinh.</p>
</div>

<div class="wrap">
    <div class="crumb">
        <a href="{{ route('content.index') }}">Bài học</a> › <a href="{{ route('content.quizzes', $lesson) }}">{{ $lesson->title }}</a> › {{ $quiz->title }}
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="toolbar">
            <strong>Nhập câu hỏi</strong>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                @if(auth()->user()?->role?->slug === 'admin')
                    <button class="btn" onclick="modal('questionModal')">＋ Thêm câu hỏi</button>
                    <button class="btn white" onclick="modal('importModal')">📄 Tải CSV / DOC</button>
                @endif
                <a class="btn white" href="{{ route('quizzes.results.download', $quiz) }}">⬇️ Tải bảng điểm</a>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success" style="margin:12px 0 0">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="flash-notice" style="margin:12px 0 0">{{ $errors->first() }}</div>
        @endif

        <div class="grid-2">
            <div class="card" style="padding:16px;box-shadow:none">
                <div class="metric">TỔNG CÂU HỎI</div>
                <div class="number">{{ $quiz->questions->count() }}</div>
            </div>
            <div class="card" style="padding:16px;box-shadow:none">
                <div class="metric">KẾT QUẢ</div>
                <div class="number">0</div>
                <p class="metric" style="margin-top:6px">Có thể tải bảng điểm sau khi học sinh làm bài.</p>
            </div>
        </div>
    </div>

    @forelse($quiz->questions as $i => $question)
        <div class="card" style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <b>Câu {{ $i + 1 }}. {{ $question->content }}</b>
                <span class="badge beginner">{{ $question->type ?? 'multiple_choice' }}</span>
            </div>
            <div class="answers" style="margin-top:14px">
                @foreach($question->answers as $answer)
                    <div class="answer {{ $answer->is_correct ? 'correct' : '' }}">
                        {{ $answer->is_correct ? '✓ ' : '' }}{{ $answer->content }}
                    </div>
                @endforeach
            </div>
            @if($question->answers->where('is_correct', true)->first()?->explanation)
                <p class="metric" style="margin-top:12px"><b>Giải thích:</b> {{ $question->answers->where('is_correct', true)->first()->explanation }}</p>
            @endif
        </div>
    @empty
        <div class="card">Chưa có câu hỏi nào. Bạn có thể thêm thủ công hoặc nhập từ file.</div>
    @endforelse
</div>

<div id="questionModal" class="modal">
    <form class="dialog" onsubmit="toast('Đã lưu câu hỏi');modal('questionModal');return false">
        <h2>Thêm câu hỏi</h2>
        <textarea required placeholder="Nội dung câu hỏi"></textarea>
        @for($i = 1; $i <= 4; $i++)
            <label><input type="radio" name="correct" required style="width:auto"> Đáp án {{ $i }}</label>
            <input required placeholder="Nhập đáp án {{ $i }}">
        @endfor
        <textarea placeholder="Giải thích"></textarea>
        <button class="btn">Lưu câu hỏi</button>
    </form>
</div>

@if(auth()->user()?->role?->slug === 'admin')
<div id="importModal" class="modal">
    <div class="dialog">
        <h2>Import câu hỏi từ file</h2>
        <p class="metric">Hỗ trợ CSV (cột: question, option_a, option_b, option_c, option_d, correct_answer, explanation) và file DOC/DOCX text.</p>
        <form action="{{ route('content.quiz.import', ['lesson' => $lesson, 'quiz' => $quiz]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card" style="padding:16px;box-shadow:none">
                <label style="font-weight:800">Chọn file</label>
                <input type="file" name="file" accept=".csv,.doc,.docx,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                <button class="btn" style="margin-top:12px" type="submit">Nhập file</button>
            </div>
        </form>
        <p class="metric">Sau khi nhập, hệ thống sẽ tạo các câu hỏi và đáp án tự động. Bạn có thể chỉnh sửa sau.</p>
        <button class="btn white" onclick="modal('importModal')" style="margin-top:12px">Đóng</button>
    </div>
</div>
@endif
@endsection
