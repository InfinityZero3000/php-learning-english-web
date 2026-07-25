@extends('layouts.app')
@section('title', 'Tạo Quiz mới')
@section('page-title', 'Thêm Quiz')

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.quizzes.index') }}">Quiz</a>
    <span class="sep"><i class="bi bi-chevron-right"></i></span>
    <span>Tạo quiz mới</span>
</div>

<form action="{{ route('admin.quizzes.store') }}" method="POST" id="quiz-form">
@csrf

<div class="row g-4">
    {{-- LEFT: Quiz info --}}
    <div class="col-lg-8">
        <div class="form-card mb-4">
            <h5 class="mb-4" style="font-weight:700;">
                <i class="bi bi-info-circle me-2" style="color:var(--primary);"></i>
                Thông tin Quiz
            </h5>

            <div class="mb-3">
                <label class="form-label">Bài học <span class="text-danger">*</span></label>
                <select name="lesson_id" class="form-select @error('lesson_id') is-invalid @enderror">
                    <option value="">— Chọn bài học —</option>
                    @foreach($lessons as $lesson)
                        <option value="{{ $lesson->id }}"
                            {{ old('lesson_id', $lessonId) == $lesson->id ? 'selected' : '' }}>
                            {{ $lesson->course->title ?? '' }} → {{ $lesson->title }}
                        </option>
                    @endforeach
                </select>
                @error('lesson_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Tiêu đề Quiz <span class="text-danger">*</span></label>
                <input type="text" name="title"
                       class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title') }}" placeholder="VD: Quiz - Động vật hoang dã">
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label">Điểm đạt (%) <span class="text-danger">*</span></label>
                    <input type="number" name="passing_score"
                           class="form-control @error('passing_score') is-invalid @enderror"
                           value="{{ old('passing_score', 60) }}" min="0" max="100">
                    @error('passing_score') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="draft"     {{ old('status','draft') === 'draft'     ? 'selected' : '' }}>📝 Bản nháp</option>
                        <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>✅ Xuất bản</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Questions section --}}
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h5 style="font-weight:700; margin:0;">
                    <i class="bi bi-list-ol me-2" style="color:var(--primary);"></i>
                    Câu hỏi
                </h5>
                <button type="button" class="btn-outline-linguist" id="add-question">
                    <i class="bi bi-plus-lg"></i> Thêm câu hỏi
                </button>
            </div>

            @error('questions')
                <div class="alert alert-danger mb-3">{{ $message }}</div>
            @enderror

            <div id="questions-container">
                {{-- Default 1 question --}}
                <div class="question-card" data-question="0">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="question-number">1</span>
                        <div class="flex-grow-1">
                            <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                            <textarea name="questions[0][content]" rows="2"
                                      class="form-control" placeholder="Nhập câu hỏi...">{{ old('questions.0.content') }}</textarea>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-question" style="margin-top:24px;" title="Xóa câu hỏi">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" style="font-size:.8rem;">Giải thích (tuỳ chọn)</label>
                        <input type="text" name="questions[0][explanation]" class="form-control form-control-sm"
                               placeholder="Giải thích sau khi trả lời...">
                    </div>

                    <div class="answers-wrapper mt-3">
                        <label class="form-label" style="font-size:.82rem; font-weight:600;">
                            Đáp án <span class="text-muted">(đánh dấu ✓ đáp án đúng)</span>
                        </label>
                        @for ($a = 0; $a < 4; $a++)
                        <div class="answer-option {{ old("questions.0.answers.{$a}.is_correct") ? 'correct' : '' }}">
                            <span style="width:22px; height:22px; border-radius:50%; background:var(--border); display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; flex-shrink:0;">
                                {{ chr(65+$a) }}
                            </span>
                            <input type="text" name="questions[0][answers][{{ $a }}][content]"
                                   class="answer-text" placeholder="Nhập đáp án {{ chr(65+$a) }}..."
                                   value="{{ old("questions.0.answers.{$a}.content") }}">
                            <label class="correct-check" title="Đáp án đúng" style="cursor:pointer; display:flex; align-items:center; gap:4px; flex-shrink:0; font-size:.8rem; color:var(--success); font-weight:600;">
                                <input type="checkbox" name="questions[0][answers][{{ $a }}][is_correct]"
                                       value="1" {{ old("questions.0.answers.{$a}.is_correct") ? 'checked' : '' }}
                                       style="width:16px; height:16px; accent-color:var(--success);">
                                Đúng
                            </label>
                        </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT: Submit panel --}}
    <div class="col-lg-4">
        <div class="form-card" style="position:sticky; top:80px;">
            <h6 style="font-weight:700; text-transform:uppercase; font-size:.75rem; letter-spacing:.5px; color:var(--text-muted); margin-bottom:16px;">
                Xuất bản
            </h6>
            <div class="d-grid gap-2">
                <button type="submit" class="btn-primary-linguist" style="justify-content:center; padding:12px;">
                    <i class="bi bi-floppy"></i> Lưu Quiz
                </button>
                <a href="{{ route('admin.quizzes.index') }}" class="btn-outline-linguist" style="justify-content:center;">
                    <i class="bi bi-x-lg"></i> Hủy
                </a>
            </div>

            <hr style="border-color:var(--border); margin:16px 0;">

            <div style="font-size:.82rem; color:var(--text-muted); line-height:1.6;">
                <i class="bi bi-info-circle me-1"></i>
                Mỗi câu hỏi cần ít nhất <strong>2 đáp án</strong> và <strong>1 đáp án đúng</strong>.
            </div>
        </div>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
// Template câu hỏi mới
function createQuestionHTML(index) {
    const letters = ['A','B','C','D'];
    const answers = letters.map((l, a) => `
        <div class="answer-option" data-answer="${a}">
            <span style="width:22px;height:22px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;">
                ${l}
            </span>
            <input type="text" name="questions[${index}][answers][${a}][content]"
                   class="answer-text" placeholder="Nhập đáp án ${l}...">
            <label class="correct-check" style="cursor:pointer;display:flex;align-items:center;gap:4px;flex-shrink:0;font-size:.8rem;color:var(--success);font-weight:600;">
                <input type="checkbox" name="questions[${index}][answers][${a}][is_correct]"
                       value="1" style="width:16px;height:16px;accent-color:var(--success);">
                Đúng
            </label>
        </div>
    `).join('');

    return `
        <div class="question-card" data-question="${index}">
            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="question-number">${index + 1}</span>
                <div class="flex-grow-1">
                    <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                    <textarea name="questions[${index}][content]" rows="2"
                              class="form-control" placeholder="Nhập câu hỏi..."></textarea>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-question"
                        style="margin-top:24px;" title="Xóa câu hỏi">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="mb-2">
                <label class="form-label" style="font-size:.8rem;">Giải thích (tuỳ chọn)</label>
                <input type="text" name="questions[${index}][explanation]"
                       class="form-control form-control-sm" placeholder="Giải thích sau khi trả lời...">
            </div>
            <div class="answers-wrapper mt-3">
                <label class="form-label" style="font-size:.82rem;font-weight:600;">
                    Đáp án <span class="text-muted">(đánh dấu ✓ đáp án đúng)</span>
                </label>
                ${answers}
            </div>
        </div>
    `;
}

// Cập nhật số thứ tự câu hỏi
function reindexQuestions() {
    document.querySelectorAll('#questions-container .question-card').forEach((card, i) => {
        card.setAttribute('data-question', i);
        card.querySelector('.question-number').textContent = i + 1;

        // Cập nhật name attributes
        card.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/questions\[\d+\]/, `questions[${i}]`);
        });
    });
}

// Thêm câu hỏi
document.getElementById('add-question').addEventListener('click', function () {
    const container = document.getElementById('questions-container');
    const count = container.querySelectorAll('.question-card').length;
    container.insertAdjacentHTML('beforeend', createQuestionHTML(count));
    reindexQuestions();
});

// Xóa câu hỏi (event delegation)
document.getElementById('questions-container').addEventListener('click', function (e) {
    if (e.target.closest('.remove-question')) {
        const card = e.target.closest('.question-card');
        if (document.querySelectorAll('#questions-container .question-card').length > 1) {
            card.remove();
            reindexQuestions();
        } else {
            alert('Quiz phải có ít nhất 1 câu hỏi!');
        }
    }
});

// Highlight đáp án đúng khi check
document.getElementById('questions-container').addEventListener('change', function (e) {
    if (e.target.type === 'checkbox' && e.target.closest('.correct-check')) {
        const option = e.target.closest('.answer-option');
        option.classList.toggle('correct', e.target.checked);
    }
});
</script>
@endpush

