@extends('layouts.app')
@section('title', $quiz->title)
@section('page-title', 'Chi tiết Quiz')

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.quizzes.index') }}">Quiz</a>
    <span class="sep"><i class="bi bi-chevron-right"></i></span>
    <span>{{ $quiz->title }}</span>
</div>

<div class="row g-4">
    {{-- LEFT: Questions --}}
    <div class="col-lg-8">
        <div class="form-card mb-4">
            <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); font-weight:600; margin-bottom:4px;">
                        {{ $quiz->lesson->course->title ?? '—' }} › {{ $quiz->lesson->title ?? '—' }}
                    </div>
                    <h3 style="font-weight:800; margin:0;">{{ $quiz->title }}</h3>
                </div>
                <span class="status-pill status-{{ $quiz->status }}">
                    <i class="bi bi-{{ $quiz->status === 'published' ? 'check-circle' : 'pencil' }}"></i>
                    {{ $quiz->status === 'published' ? 'Xuất bản' : 'Bản nháp' }}
                </span>
            </div>

            {{-- Stats row --}}
            <div class="d-flex gap-4 mb-4 p-3 rounded" style="background:var(--primary-light);">
                <div class="text-center">
                    <div style="font-size:1.4rem; font-weight:800; color:var(--primary);">{{ $quiz->questions->count() }}</div>
                    <div style="font-size:.75rem; color:var(--text-muted); font-weight:600;">CÂU HỎI</div>
                </div>
                <div class="text-center">
                    <div style="font-size:1.4rem; font-weight:800; color:var(--success);">{{ $quiz->passing_score }}%</div>
                    <div style="font-size:.75rem; color:var(--text-muted); font-weight:600;">ĐIỂM ĐẠT</div>
                </div>
                <div class="text-center">
                    <div style="font-size:1.4rem; font-weight:800; color:var(--warning);">{{ $quiz->attempts->count() }}</div>
                    <div style="font-size:.75rem; color:var(--text-muted); font-weight:600;">LÀM BÀI</div>
                </div>
            </div>
        </div>

        {{-- Questions list --}}
        @foreach($quiz->questions->sortBy('sort_order') as $i => $question)
        <div class="form-card mb-3">
            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="question-number">{{ $i + 1 }}</span>
                <div class="flex-grow-1">
                    <p style="font-weight:600; font-size:.95rem; margin:0; line-height:1.5;">
                        {{ $question->content }}
                    </p>
                    @if($question->explanation)
                        <div class="mt-2 p-2 rounded" style="background:#fef3c7; font-size:.8rem; color:#92400e;">
                            <i class="bi bi-lightbulb me-1"></i>
                            <em>{{ $question->explanation }}</em>
                        </div>
                    @endif
                </div>
            </div>

            <div class="ms-5">
                @php $letters = ['A','B','C','D','E','F']; @endphp
                @foreach($question->answers as $ai => $answer)
                <div class="d-flex align-items-center gap-3 p-2 rounded mb-2"
                     style="background:{{ $answer->is_correct ? '#f0fdf4' : '#fafafa' }}; border:1.5px solid {{ $answer->is_correct ? '#6ee7b7' : 'var(--border)' }};">
                    <span style="width:24px; height:24px; border-radius:50%; background:{{ $answer->is_correct ? 'var(--success)' : 'var(--border)' }}; color:{{ $answer->is_correct ? '#fff' : 'var(--text-muted)' }}; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; flex-shrink:0;">
                        {{ $letters[$ai] ?? $ai+1 }}
                    </span>
                    <span style="font-size:.88rem; flex:1;">{{ $answer->content }}</span>
                    @if($answer->is_correct)
                        <span style="color:var(--success); font-size:.78rem; font-weight:700;">
                            <i class="bi bi-check-circle-fill"></i> ĐÚNG
                        </span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- RIGHT: Actions --}}
    <div class="col-lg-4">
        <div class="form-card mb-3">
            <h6 style="font-weight:700; text-transform:uppercase; font-size:.75rem; letter-spacing:.5px; color:var(--text-muted); margin-bottom:16px;">
                Thao tác
            </h6>
            <div class="d-grid gap-2">
                <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn-primary-linguist" style="justify-content:center;">
                    <i class="bi bi-pencil"></i> Chỉnh sửa Quiz
                </a>
                <a href="{{ route('admin.lessons.show', $quiz->lesson) }}" class="btn-outline-linguist" style="justify-content:center;">
                    <i class="bi bi-arrow-left"></i> Về bài học
                </a>
                <form action="{{ route('admin.quizzes.destroy', $quiz) }}" method="POST"
                      onsubmit="return confirm('Xóa quiz này? Tất cả câu hỏi sẽ bị xóa!')">
                    @csrf @method('DELETE')
                    <button class="btn-danger-sm w-100" style="justify-content:center; padding:9px;">
                        <i class="bi bi-trash"></i> Xóa Quiz
                    </button>
                </form>
            </div>
        </div>

        <div class="form-card">
            <h6 style="font-weight:700; text-transform:uppercase; font-size:.75rem; letter-spacing:.5px; color:var(--text-muted); margin-bottom:16px;">
                Thông tin
            </h6>
            <dl style="font-size:.88rem; margin:0;">
                <dt class="text-muted mb-1">Bài học</dt>
                <dd class="mb-3">{{ $quiz->lesson->title ?? '—' }}</dd>

                <dt class="text-muted mb-1">Khóa học</dt>
                <dd class="mb-3">{{ $quiz->lesson->course->title ?? '—' }}</dd>

                <dt class="text-muted mb-1">Ngày tạo</dt>
                <dd class="mb-3">{{ $quiz->created_at->format('d/m/Y H:i') }}</dd>

                <dt class="text-muted mb-1">Cập nhật</dt>
                <dd class="mb-0">{{ $quiz->updated_at->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection

