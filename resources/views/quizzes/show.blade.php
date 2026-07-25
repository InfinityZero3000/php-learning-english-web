@extends('layouts.app')

@section('top', 'Làm Quiz')
@section('breadcrumb', $quiz->lesson->course->name . ' / ' . $quiz->lesson->title . ' / ' . $quiz->title)

@section('content')
@php
    $questions = $quiz->questions->sortBy('sort_order')->values();
    $totalQuestions = $questions->count();
    $passingScore = $quiz->passing_score;
    $timeLimit = $quiz->time_limit ?? 0;
@endphp

<div class="page-header">
    <h2>📝 {{ $quiz->title }}</h2>
    <p>{{ $quiz->lesson->course->name }} &rarr; {{ $quiz->lesson->title }} &bull; {{ $totalQuestions }} câu hỏi &bull; Điểm đạt: {{ $passingScore }}%</p>
</div>

@if($totalQuestions === 0)
    <div class="empty-state">
        <i data-lucide="help-circle" style="width:56px;height:56px;color:#cbd5e1;"></i>
        <h3>Quiz này chưa có câu hỏi</h3>
        <p>Hãy quay lại sau khi câu hỏi được thêm vào.</p>
    </div>
@else
    {{-- Quiz Header: Progress + Timer --}}
    <div class="card mb-4" id="quiz-header">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="stat-icon purple" style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="help-circle" style="width:22px;height:22px;"></i>
                </div>
                <div>
                    <div style="font-size:22px;font-weight:900;" id="question-counter">1 / {{ $totalQuestions }}</div>
                    <div style="font-size:11px;color:var(--gray);text-transform:uppercase;letter-spacing:.06em;">Câu hỏi</div>
                </div>
            </div>
            @if($timeLimit > 0)
            <div style="display:flex;align-items:center;gap:16px;">
                <div class="stat-icon amber" style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="clock" style="width:22px;height:22px;"></i>
                </div>
                <div>
                    <div style="font-size:22px;font-weight:900;color:var(--amber);" id="timer-display">{{ $timeLimit }}:00</div>
                    <div style="font-size:11px;color:var(--gray);text-transform:uppercase;letter-spacing:.06em;">Còn lại</div>
                </div>
            </div>
            @endif
        </div>
        <div class="progress-bar mt-4" style="height:8px;">
            <div class="progress-fill blue" id="progress-fill" style="width: {{ $totalQuestions > 0 ? round(1 / $totalQuestions * 100) : 0 }}%;"></div>
        </div>
    </div>

    <form action="{{ route('quizzes.submit', $quiz) }}" method="POST" id="quiz-form">
        @csrf

        <div id="questions-container">
            @foreach($questions as $idx => $question)
            <div class="card question-slide" data-index="{{ $idx }}" style="{{ $idx > 0 ? 'display:none;' : '' }}">
                <div class="card-header">
                    <span class="badge badge-enriched">Câu {{ $idx + 1 }}</span>
                    @if($question->point_value)
                        <span class="badge badge-default">{{ $question->point_value }} điểm</span>
                    @endif
                </div>
                <h3 style="font-size:18px;font-weight:800;margin-bottom:20px;line-height:1.5;">{{ $question->content }}</h3>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($question->answers->sortBy('sort_order') as $answer)
                    <label class="quiz-option" data-question="{{ $question->id }}" data-answer="{{ $answer->id }}">
                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $answer->id }}" style="display:none;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="width:32px;height:32px;border-radius:50%;border:2px solid var(--line);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;transition:all .15s;" class="option-badge">{{ chr(65 + $loop->index) }}</span>
                            <span>{{ $answer->content }}</span>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        {{-- Navigation --}}
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:24px;gap:12px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="goToPrev()" id="btn-prev" disabled>
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Trước
            </button>
            <span style="font-weight:700;font-size:14px;color:var(--gray);" id="nav-progress">1 / {{ $totalQuestions }}</span>
            @if($totalQuestions > 1)
            <button type="button" class="btn btn-outline btn-sm" onclick="goToNext()" id="btn-next">
                Tiếp <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
            </button>
            @endif
        </div>

        {{-- Dots --}}
        <div style="display:flex;justify-content:center;gap:8px;margin-top:16px;flex-wrap:wrap;" id="question-dots">
            @foreach($questions as $idx => $q)
            <span class="question-dot" data-index="{{ $idx }}" style="width:12px;height:12px;border-radius:50%;background:var(--line);cursor:pointer;transition:all .15s;{{ $idx === 0 ? 'background:var(--brand);transform:scale(1.3);' : '' }}" onclick="goToSlide({{ $idx }})" title="Câu {{ $idx + 1 }}"></span>
            @endforeach
        </div>

        {{-- Submit --}}
        <div style="text-align:center;margin-top:24px;">
            <button type="button" class="btn btn-primary btn-lg" onclick="submitQuiz()" id="btn-submit" {{ $totalQuestions > 1 ? 'style=display:none;' : '' }}>
                <i data-lucide="send" style="width:18px;height:18px;"></i> Nộp bài
            </button>
        </div>
    </form>

    {{-- Confirmation Modal --}}
    <div class="modal-overlay" id="confirm-modal">
        <div class="modal">
            <h3>📋 Xác nhận nộp bài</h3>
            <p style="color:var(--gray);margin-bottom:12px;">Bạn có chắc chắn muốn nộp bài?</p>
            <div id="unanswered-warning" style="background:var(--amber-soft);padding:10px 14px;border-radius:10px;font-size:13px;color:#92400e;font-weight:600;display:none;margin-bottom:12px;">
                ⚠️ Bạn còn <span id="unanswered-count">0</span> câu chưa trả lời.
            </div>
            <div class="modal-actions">
                <button class="btn btn-ghost btn-sm" onclick="closeModal('confirm-modal')">Hủy</button>
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('quiz-form').submit();">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Nộp bài
                </button>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    const totalQuestions = {{ $totalQuestions }};
    const timeLimit = {{ $timeLimit }};
    let currentSlide = 0;
    let timerInterval = null;
    let remainingSeconds = timeLimit * 60;

    function showSlide(index) {
        document.querySelectorAll('.question-slide').forEach((el, i) => {
            el.style.display = i === index ? '' : 'none';
        });
        currentSlide = index;
        updateNav();
    }

    function goToNext() {
        if (currentSlide < totalQuestions - 1) showSlide(currentSlide + 1);
    }

    function goToPrev() {
        if (currentSlide > 0) showSlide(currentSlide - 1);
    }

    function goToSlide(index) {
        showSlide(index);
    }

    function updateNav() {
        document.getElementById('btn-prev').disabled = currentSlide === 0;
        const btnNext = document.getElementById('btn-next');
        const btnSubmit = document.getElementById('btn-submit');
        if (btnNext) btnNext.style.display = currentSlide === totalQuestions - 1 ? 'none' : '';
        if (btnSubmit) btnSubmit.style.display = currentSlide === totalQuestions - 1 ? '' : 'none';
        document.getElementById('nav-progress').textContent = `${currentSlide + 1} / ${totalQuestions}`;
        document.getElementById('question-counter').textContent = `${currentSlide + 1} / ${totalQuestions}`;
        document.getElementById('progress-fill').style.width = `${((currentSlide + 1) / totalQuestions) * 100}%`;

        document.querySelectorAll('.question-dot').forEach((dot, i) => {
            dot.style.background = i <= currentSlide ? 'var(--brand)' : 'var(--line)';
            dot.style.transform = i === currentSlide ? 'scale(1.3)' : 'scale(1)';
        });
    }

    // Option selection
    document.querySelectorAll('.quiz-option').forEach(option => {
        option.addEventListener('click', function() {
            const questionId = this.dataset.question;
            document.querySelectorAll(`.quiz-option[data-question="${questionId}"]`).forEach(opt => {
                opt.classList.remove('selected');
                const badge = opt.querySelector('.option-badge');
                if (badge) { badge.style.background = ''; badge.style.borderColor = 'var(--line)'; badge.style.color = ''; }
            });
            this.classList.add('selected');
            const badge = this.querySelector('.option-badge');
            if (badge) { badge.style.background = 'var(--brand)'; badge.style.borderColor = 'var(--brand)'; badge.style.color = '#fff'; }
            this.querySelector('input[type="radio"]').checked = true;
            document.querySelectorAll('.question-dot')[currentSlide].style.background = 'var(--green)';
        });
    });

    // Submit
    function submitQuiz() {
        const radios = document.querySelectorAll('input[type="radio"]:checked');
        const unanswered = totalQuestions - radios.length;
        if (unanswered > 0) {
            document.getElementById('unanswered-warning').style.display = '';
            document.getElementById('unanswered-count').textContent = unanswered;
        } else {
            document.getElementById('unanswered-warning').style.display = 'none';
        }
        openModal('confirm-modal');
    }

    // Keyboard
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('confirm-modal').classList.contains('open')) return;
        if (e.key === 'ArrowRight') goToNext();
        else if (e.key === 'ArrowLeft') goToPrev();
        else if (e.key === 'a' || e.key === 'A') selectOption(0);
        else if (e.key === 'b' || e.key === 'B') selectOption(1);
        else if (e.key === 'c' || e.key === 'C') selectOption(2);
        else if (e.key === 'd' || e.key === 'D') selectOption(3);
    });

    function selectOption(optIndex) {
        const slide = document.querySelectorAll('.question-slide')[currentSlide];
        if (!slide) return;
        const options = slide.querySelectorAll('.quiz-option');
        if (options[optIndex]) options[optIndex].click();
    }

    // Timer
    if (timeLimit > 0) {
        function updateTimerDisplay() {
            const mins = Math.floor(remainingSeconds / 60);
            const secs = remainingSeconds % 60;
            document.getElementById('timer-display').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
            if (remainingSeconds <= 60) {
                document.getElementById('timer-display').style.color = 'var(--red)';
            }
        }

        timerInterval = setInterval(function() {
            remainingSeconds--;
            updateTimerDisplay();
            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                alert('⏰ Hết thời gian! Bài làm sẽ được tự động nộp.');
                document.getElementById('quiz-form').submit();
            }
        }, 1000);
    }

    // Prevent accidental leave
    window.addEventListener('beforeunload', function(e) {
        const checked = document.querySelectorAll('input[type="radio"]:checked').length;
        if (checked > 0) {
            e.preventDefault();
            e.returnValue = 'Bạn có câu trả lời chưa được nộp. Bạn có chắc muốn rời đi?';
            return e.returnValue;
        }
    });

    document.getElementById('quiz-form').addEventListener('submit', function() {
        window.removeEventListener('beforeunload', () => {});
    });

    lucide.createIcons();
</script>
@endpush