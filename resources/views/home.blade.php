@extends('layouts.app')
@section('title', 'Trang chủ')
@section('page-title', 'Dashboard')

@section('content')
{{-- Welcome banner --}}
<div class="mb-5 p-4 rounded-3" style="background:linear-gradient(135deg, #1a3a5c 0%, #1e6fa8 100%); color:#fff; position:relative; overflow:hidden;">
    <div style="position:absolute; right:-20px; top:-20px; width:200px; height:200px; background:rgba(255,255,255,.05); border-radius:50%;"></div>
    <div style="position:absolute; right:60px; bottom:-40px; width:140px; height:140px; background:rgba(255,255,255,.05); border-radius:50%;"></div>
    <div style="position:relative; z-index:1;">
        <div style="font-size:2rem; margin-bottom:8px;">👋</div>
        <h2 style="font-weight:800; font-size:1.6rem; margin-bottom:6px;">
            Chào mừng đến với LexiLingo!
        </h2>
        <p style="opacity:.85; margin-bottom:20px; font-size:.95rem; max-width:500px;">
            Nền tảng học tiếng Anh hiện đại — Bài học, Quiz và từ vựng trong một nơi.
        </p>
        <div class="d-flex gap-3 flex-wrap">
            <a href="{{ route('admin.lessons.index') }}" class="btn btn-light fw-600" style="font-weight:600; border-radius:8px;">
                <i class="bi bi-book-half me-1"></i> Xem Bài học
            </a>
            <a href="{{ route('admin.quizzes.index') }}" class="btn btn-outline-light fw-600" style="font-weight:600; border-radius:8px;">
                <i class="bi bi-patch-question me-1"></i> Làm Quiz
            </a>
        </div>
    </div>
</div>

{{-- Quick stats --}}
<div class="row g-3 mb-5">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-book-half"></i></div>
            <div>
                <div class="stat-value">{{ \App\Models\Lesson::count() }}</div>
                <div class="stat-label">Bài học</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="bi bi-patch-question-fill"></i></div>
            <div>
                <div class="stat-value">{{ \App\Models\Quiz::count() }}</div>
                <div class="stat-label">Quiz</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-card-text"></i></div>
            <div>
                <div class="stat-value">{{ \App\Models\Vocabulary::count() }}</div>
                <div class="stat-label">Từ vựng</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-people"></i></div>
            <div>
                <div class="stat-value">{{ \App\Models\User::count() }}</div>
                <div class="stat-label">Người dùng</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Recent Lessons --}}
    <div class="col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 style="font-weight:700; margin:0;">
                <i class="bi bi-book-half me-2" style="color:var(--primary);"></i>
                Bài học gần đây
            </h5>
            <a href="{{ route('admin.lessons.index') }}" style="font-size:.83rem; color:var(--primary); font-weight:600; text-decoration:none;">
                Xem tất cả <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="table-linguist">
            <table class="table mb-0">
                <tbody>
                    @forelse(\App\Models\Lesson::with('course')->latest()->take(5)->get() as $lesson)
                    <tr>
                        <td style="padding:12px 16px;">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:36px; height:36px; border-radius:8px; background:var(--primary-light); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <i class="bi bi-journal-text" style="color:var(--primary);"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600; font-size:.88rem;">{{ $lesson->title }}</div>
                                    <div style="font-size:.75rem; color:var(--text-muted);">{{ $lesson->course->title ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:12px 16px; vertical-align:middle; text-align:right;">
                            <span class="status-pill status-{{ $lesson->status }}">
                                {{ $lesson->status === 'published' ? 'Xuất bản' : 'Nháp' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td class="text-center text-muted py-4">Chưa có bài học nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent Quizzes --}}
    <div class="col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 style="font-weight:700; margin:0;">
                <i class="bi bi-patch-question-fill me-2" style="color:var(--primary);"></i>
                Quiz gần đây
            </h5>
            <a href="{{ route('admin.quizzes.index') }}" style="font-size:.83rem; color:var(--primary); font-weight:600; text-decoration:none;">
                Xem tất cả <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="table-linguist">
            <table class="table mb-0">
                <tbody>
                    @forelse(\App\Models\Quiz::with('lesson')->withCount('questions')->latest()->take(5)->get() as $quiz)
                    <tr>
                        <td style="padding:12px 16px;">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:36px; height:36px; border-radius:8px; background:#fef3c7; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <i class="bi bi-patch-question-fill" style="color:#92400e;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600; font-size:.88rem;">{{ $quiz->title }}</div>
                                    <div style="font-size:.75rem; color:var(--text-muted);">
                                        {{ $quiz->questions_count }} câu · Đạt {{ $quiz->passing_score }}%
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:12px 16px; vertical-align:middle; text-align:right;">
                            <span class="status-pill status-{{ $quiz->status }}">
                                {{ $quiz->status === 'published' ? 'Xuất bản' : 'Nháp' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td class="text-center text-muted py-4">Chưa có quiz nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

