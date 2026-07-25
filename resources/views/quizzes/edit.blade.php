@extends('layouts.app')
@section('title', 'Sửa: ' . $quiz->title)
@section('page-title', 'Chỉnh sửa Quiz')

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.quizzes.index') }}">Quiz</a>
    <span class="sep"><i class="bi bi-chevron-right"></i></span>
    <a href="{{ route('admin.quizzes.show', $quiz) }}">{{ Str::limit($quiz->title, 40) }}</a>
    <span class="sep"><i class="bi bi-chevron-right"></i></span>
    <span>Chỉnh sửa</span>
</div>

<form action="{{ route('admin.quizzes.update', $quiz) }}" method="POST" id="quiz-form">
@csrf
@method('PUT')

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Quiz Info --}}
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
                            {{ old('lesson_id', $quiz->lesson_id) == $lesson->id ? 'selected' : '' }}>
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
                       value="{{ old('title', $quiz->title) }}">
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label">Điểm đạt (%)</label>
                    <input type="number" name="passing_score"
                           class="form-control @error('passing_score') is-invalid @enderror"
                           value="{{ old('passing_score', $quiz->passing_score) }}" min="0" max="100">
                    @error('passing_score') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="draft"     {{ old('status', $quiz->status) === 'draft'     ? 'selected' : '' }}>📝 Bản nháp</option>
                        <option value="published" {{ old('status', $quiz->status) === 'published' ? 'selected' : '' }}>✅ Xuất bản</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Questions --}}
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h5 style="font-weight:700; margin:0;">
                    <i class="bi bi-list-ol me-2" style="color:var(--primary);"></i>
                    Câu hỏi ({{ $quiz->questions->count() }})
                </h5>
                <button type="button" class="btn-outline-linguist" id="add-question">
                    <i class="bi bi-plus-lg"></i> Thêm câu hỏi
                </button>
            </div>

            <div id="questions-container">
                @foreach($quiz->questions->sortBy('sort_order') as $qi => $question)
                <div class="question-card" data-question="{{ $qi }}">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="question-number">{{ $qi + 1 }}</span>
                        <div class="flex-grow-1">
                            <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                            <textarea name="questions[{{ $qi }}][content]" rows="2"
                                      class="form-control">{{ old("questions.{$qi}.content", $question->content) }}</textarea>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-question"
                                style="margin-top:24px;" title="Xóa câu hỏi">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:.8rem;">Giải thích (tuỳ chọn)</label>
                        <input type="text" name="questions[{{ $qi }}][explanation]"
                               class="form-control form-control-sm"
                               value="{{ old("questions.{$qi}.explanation", $question->explanation) }}"
                               placeholder="Giải thích sau khi trả lời...">
                    </div>
                    <div class="answers-wrapper mt-3">
                        <label class="form-label" style="font-size:.82rem; font-weight:600;">
                            Đáp án <span class="text-muted">(đánh dấu ✓ đáp án đúng)</span>
                        </label>
                        @php $letters = ['A','B','C','D']; @endphp
                        @foreach($question->answers as $ai => $answer)
                        <div class="answer-option {{ $answer->is_correct ? 'correct' : '' }}">
                            <span style="width:22px;height:22px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;">
                                {{ $letters[$ai] ?? $ai+1 }}
                            </span>
                            <input type="text" name="questions[{{ $qi }}][answers][{{ $ai }}][content]"
                                   class="answer-text"
                                   value="{{ old("questions.{$qi}.answers.{$ai}.content", $answer->content) }}"
                                   placeholder="Nhập đáp án...">
                            <label class="correct-check" style="cursor:pointer;display:flex;align-items:center;gap:4px;flex-shrink:0;font-size:.8rem;color:var(--success);font-weight:600;">
                                <input type="checkbox" name="questions[{{ $qi }}][answers][{{ $ai }}][is_correct]"
                                       value="1"
                                       {{ old("questions.{$qi}.answers.{$ai}.is_correct", $answer->is_correct) ? 'checked' : '' }}
                                       style="width:16px;height:16px;accent-color:var(--success);">
                                Đúng
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RIGHT: Submit --}}
    <div class="col-lg-4">
        <div class="form-card" style="position:sticky; top:80px;">
            <h6 style="font-weight:700; text-transform:uppercase; font-size:.75rem; letter-spacing:.5px; color:var(--text-muted); margin-bottom:16px;">
                Cập nhật
            </h6>
            <div class="d-grid gap-2">
                <button type="submit" class="btn-primary-linguist" style="justify-content:center; padding:12px;">
                    <i class="bi bi-floppy"></i> Lưu thay đổi
                </button>
                <a href="{{ route('admin.quizzes.show', $quiz) }}" class="btn-outline-linguist" style="justify-content:center;">
                    <i class="bi bi-x-lg"></i> Hủy
                </a>
            </div>
            <hr style="border-color:var(--border); margin:16px 0;">
            <div class="text-danger" style="font-size:.8rem;">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Lưu sẽ xóa và tạo lại toàn bộ câu hỏi.
            </div>
        </div>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
function createQuestionHTML(index) {
    const letters = ['A','B','C','D'];
    const answers = letters.map((l, a) => `
        <div class="answer-option" data-answer="${a}">
            <span style="width:22px;height:22px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;">${l}</span>
            <input type="text" name="questions[${index}][answers][${a}][content]" class="answer-text" placeholder="Nhập đáp án ${l}...">
            <label class="correct-check" style="cursor:pointer;display:flex;align-items:center;gap:4px;flex-shrink:0;font-size:.8rem;color:var(--success);font-weight:600;">
                <input type="checkbox" name="questions[${index}][answers][${a}][is_correct]" value="1" style="width:16px;height:16px;accent-color:var(--success);">
                Đúng
            </label>
        </div>`).join('');
    return `
        <div class="question-card" data-question="${index}">
            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="question-number">${index+1}</span>
                <div class="flex-grow-1">
                    <label class="form-label">Nội dung câu hỏi <span class="text-danger">*</span></label>
                    <textarea name="questions[${index}][content]" rows="2" class="form-control" placeholder="Nhập câu hỏi..."></textarea>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-question" style="margin-top:24px;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="mb-2">
                <label class="form-label" style="font-size:.8rem;">Giải thích (tuỳ chọn)</label>
                <input type="text" name="questions[${index}][explanation]" class="form-control form-control-sm" placeholder="Giải thích sau khi trả lời...">
            </div>
            <div class="answers-wrapper mt-3">
                <label class="form-label" style="font-size:.82rem;font-weight:600;">Đáp án <span class="text-muted">(đánh dấu ✓ đáp án đúng)</span></label>
                ${answers}
            </div>
        </div>`;
}

function reindexQuestions() {
    document.querySelectorAll('#questions-container .question-card').forEach((card, i) => {
        card.setAttribute('data-question', i);
        card.querySelector('.question-number').textContent = i + 1;
        card.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/questions\[\d+\]/, `questions[${i}]`);
        });
    });
}

document.getElementById('add-question').addEventListener('click', function () {
    const container = document.getElementById('questions-container');
    const count = container.querySelectorAll('.question-card').length;
    container.insertAdjacentHTML('beforeend', createQuestionHTML(count));
    reindexQuestions();
});

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

document.getElementById('questions-container').addEventListener('change', function (e) {
    if (e.target.type === 'checkbox' && e.target.closest('.correct-check')) {
        const option = e.target.closest('.answer-option');
        option.classList.toggle('correct', e.target.checked);
    }
});
</script>
@endpush

