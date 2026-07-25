@extends('layouts.app')

@section('title', 'Danh sách bài kiểm tra')

@section('topbar')
<div class="topbar">
    <div class="topbar-left">
        <h1>🧠 Bài kiểm tra</h1>
        <span class="breadcrumb">/ Khám phá Quiz</span>
    </div>
    <div class="topbar-actions">
        <span style="font-size:13px;color:var(--gray);font-weight:600;">
            📋 {{ $quizzes->total() }} bài kiểm tra
        </span>
    </div>
</div>
@endsection

@section('content')
{{-- Hero Banner --}}
<div class="hero">
    <h1>Thử thách bản thân</h1>
    <p>Làm bài kiểm tra để củng cố kiến thức, theo dõi tiến độ và nâng cao kỹ năng tiếng Anh của bạn mỗi ngày.</p>
    <div class="hero-stats">
        <div class="hero-stat">
            <div class="val">{{ $quizzes->total() }}</div>
            <div class="lbl">Quiz</div>
        </div>
        @if($quizzes->isNotEmpty())
        <div class="hero-stat">
            <div class="val">{{ $quizzes->sum(fn($q) => $q->questions->count()) }}</div>
            <div class="lbl">Câu hỏi</div>
        </div>
        <div class="hero-stat">
            <div class="val">{{ round($quizzes->avg('passing_score')) }}%</div>
            <div class="lbl">Điểm đạt TB</div>
        </div>
        @endif
    </div>
</div>

{{-- Alert --}}
@if(session('success'))
    <div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;border:1px solid #a7f3d0;">
        ✅ {{ session('success') }}
    </div>
@endif

{{-- Toolbar --}}
<div class="toolbar">
    <div class="search-box">
        <i data-lucide="search"></i>
        <input type="text" id="quizSearch" placeholder="Tìm kiếm quiz..." onkeyup="filterQuizzes()">
    </div>
    <div class="toolbar-spacer"></div>
    <select id="sortOrder" class="form-select" style="max-width:200px;" onchange="sortQuizzes()">
        <option value="newest">Mới nhất</option>
        <option value="oldest">Cũ nhất</option>
        <option value="most_questions">Nhiều câu hỏi nhất</option>
        <option value="highest_score">Điểm đạt cao nhất</option>
    </select>
</div>

{{-- Empty State --}}
@if($quizzes->isEmpty())
    <div class="empty-state">
        <i data-lucide="file-question"></i>
        <h3>Chưa có bài kiểm tra nào</h3>
        <p>Hãy quay lại sau khi có bài kiểm tra mới được thêm vào.</p>
    </div>
@else
    {{-- Quiz Cards Grid --}}
    <div class="grid" id="quizGrid">
        @foreach($quizzes as $quiz)
            <div class="card quiz-card" data-title="{{ strtolower($quiz->title) }}" data-date="{{ $quiz->created_at->timestamp }}" data-questions="{{ $quiz->questions->count() }}" data-passing="{{ $quiz->passing_score }}">
                {{-- Card accent bar --}}
                <div class="quiz-accent" style="background: linear-gradient(135deg, {{ ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6'][$loop->index % 6] }}, {{ ['#818cf8','#a78bfa','#f472b6','#fbbf24','#34d399','#60a5fa'][$loop->index % 6] }}); height: 5px; border-radius: var(--radius) var(--radius) 0 0; margin: -24px -24px 20px -24px;"></div>

                {{-- Quiz Icon + Title --}}
                <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:12px;">
                    <div class="quiz-icon" style="
                        width:48px;height:48px;border-radius:14px;
                        background: {{ ['#eef2ff','#ede9fe','#fce7f3','#fef3c7','#d1fae5','#dbeafe'][$loop->index % 6] }};
                        color: {{ ['#4f46e5','#7c3aed','#db2777','#d97706','#059669','#2563eb'][$loop->index % 6] }};
                        display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;
                    ">
                        {{ ['🧩','🎯','⚡','🔥','💡','🌟'][$loop->index % 6] }}
                    </div>
                    <div style="flex:1;">
                        <h3 style="font-size:16px;font-weight:800;color:var(--ink);margin:0 0 2px;line-height:1.3;">
                            {{ $quiz->title }}
                        </h3>
                        @if($quiz->lesson)
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <span style="font-size:12px;color:var(--gray);font-weight:600;">
                                    📖 {{ $quiz->lesson->title }}
                                </span>
                                @if($quiz->lesson->course)
                                    <span style="font-size:11px;color:#94a3b8;">•</span>
                                    <span style="font-size:12px;color:#94a3b8;font-weight:500;">
                                        {{ $quiz->lesson->course->title }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Stats Row --}}
                <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:18px;">❓</span>
                        <div>
                            <div style="font-size:18px;font-weight:900;color:var(--ink);line-height:1;">{{ $quiz->questions->count() }}</div>
                            <div style="font-size:11px;color:var(--gray);font-weight:600;">câu hỏi</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:18px;">🏆</span>
                        <div>
                            <div style="font-size:18px;font-weight:900;color:var(--ink);line-height:1;">{{ $quiz->passing_score }}%</div>
                            <div style="font-size:11px;color:var(--gray);font-weight:600;">điểm đạt</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:18px;">📅</span>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:var(--ink);line-height:1;">{{ $quiz->created_at->format('d/m/Y') }}</div>
                            <div style="font-size:11px;color:var(--gray);font-weight:600;">tạo lúc</div>
                        </div>
                    </div>
                </div>

                {{-- Difficulty Indicator --}}
                @php
                    $difficultyLabel = 'Dễ';
                    $difficultyColor = '#10b981';
                    $difficultyBg = '#d1fae5';
                    if ($quiz->passing_score >= 70) {
                        $difficultyLabel = 'Khó';
                        $difficultyColor = '#ef4444';
                        $difficultyBg = '#fee2e2';
                    } elseif ($quiz->passing_score >= 50) {
                        $difficultyLabel = 'Trung bình';
                        $difficultyColor = '#f59e0b';
                        $difficultyBg = '#fef3c7';
                    }
                @endphp
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span class="badge" style="background:{{ $difficultyBg }};color:{{ $difficultyColor }};font-size:12px;padding:5px 12px;">
                        {{ $difficultyLabel }}
                    </span>
                    <a href="{{ route('quizzes.play', $quiz) }}" class="btn btn-primary btn-sm" style="font-size:13px;padding:8px 18px;">
                        🚀 Làm bài
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    {{-- No Results (hidden by default) --}}
    <div id="noResults" class="empty-state" style="display:none;">
        <i data-lucide="search-x"></i>
        <h3>Không tìm thấy quiz phù hợp</h3>
        <p>Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc.</p>
    </div>

    {{-- Pagination --}}
    @if($quizzes->hasPages())
        <div class="pagination">
            {{ $quizzes->links() }}
        </div>
    @endif
@endif
@endsection

@push('scripts')
<script>
    lucide.createIcons();

    function filterQuizzes() {
        const query = document.getElementById('quizSearch').value.toLowerCase();
        const cards = document.querySelectorAll('.quiz-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const title = card.getAttribute('data-title') || '';
            if (title.includes(query)) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        document.getElementById('noResults').style.display = visibleCount === 0 ? '' : 'none';
    }

    function sortQuizzes() {
        const order = document.getElementById('sortOrder').value;
        const grid = document.getElementById('quizGrid');
        const cards = Array.from(grid.querySelectorAll('.quiz-card'));

        cards.sort((a, b) => {
            switch (order) {
                case 'newest':
                    return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
                case 'oldest':
                    return parseInt(a.getAttribute('data-date')) - parseInt(b.getAttribute('data-date'));
                case 'most_questions':
                    return parseInt(b.getAttribute('data-questions')) - parseInt(a.getAttribute('data-questions'));
                case 'highest_score':
                    return parseInt(b.getAttribute('data-passing')) - parseInt(a.getAttribute('data-passing'));
                default:
                    return 0;
            }
        });

        cards.forEach(card => grid.appendChild(card));
    }
</script>
@endpush