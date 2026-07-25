@extends('layouts.app')
@section('title', 'Tiến Độ Học Tập')
@section('page-title', 'Tiến Độ Của Tôi')

@section('content')

{{-- Thống kê nhanh --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:14px; border-left:4px solid #4f46e5 !important">
            <div style="font-size:2rem; font-weight:800; color:#4f46e5">{{ $completedLessons->count() }}</div>
            <div class="text-muted small">/ {{ $totalLessons }} Bài học hoàn thành</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:14px; border-left:4px solid #10b981 !important">
            <div style="font-size:2rem; font-weight:800; color:#10b981">{{ $passedAttempts }}</div>
            <div class="text-muted small">/ {{ $totalAttempts }} Quiz đã qua</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:14px; border-left:4px solid #f59e0b !important">
            <div style="font-size:2rem; font-weight:800; color:#f59e0b">{{ $avgScore }}%</div>
            <div class="text-muted small">Điểm trung bình</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3" style="border-radius:14px; border-left:4px solid #6366f1 !important">
            @php
                $overall = $totalLessons > 0 ? round($completedLessons->count() / $totalLessons * 100) : 0;
            @endphp
            <div style="font-size:2rem; font-weight:800; color:#6366f1">{{ $overall }}%</div>
            <div class="text-muted small">Tiến độ tổng thể</div>
        </div>
    </div>
</div>

{{-- Tiến độ từng Khóa học --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Tiến độ theo Khóa học</h5>
        @forelse($courses as $course)
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
                <span class="fw-semibold">{{ $course->title }}</span>
                <span class="text-muted small">{{ $course->done }}/{{ $course->total }} bài · {{ $course->pct }}%</span>
            </div>
            <div class="progress" style="height:10px; border-radius:10px">
                <div class="progress-bar" role="progressbar"
                     style="width:{{ $course->pct }}%; background: linear-gradient(90deg,#667eea,#764ba2); border-radius:10px"
                     aria-valuenow="{{ $course->pct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        @empty
        <p class="text-muted">Chưa có dữ liệu tiến độ.</p>
        @endforelse
    </div>
</div>

<div class="row g-3">
    {{-- Bài học đã hoàn thành --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-book-half me-2 text-success"></i>Bài học đã hoàn thành</h5>
                @forelse($completedLessons as $prog)
                <div class="d-flex align-items-center mb-2 p-2 rounded" style="background:#f0fdf4">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    <div>
                        <div class="fw-semibold" style="font-size:.9rem">{{ $prog->lesson->title ?? 'N/A' }}</div>
                        <div class="text-muted" style="font-size:.76rem">{{ $prog->completed_at?->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
                @empty
                <p class="text-muted">Bạn chưa hoàn thành bài học nào. Hãy vào học và đánh dấu hoàn thành nhé!</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Lịch sử làm Quiz --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-clipboard2-check me-2 text-warning"></i>Lịch sử làm Quiz</h5>
                @forelse($attempts as $attempt)
                @php $passed = $attempt->score >= ($attempt->quiz->passing_score ?? 60); @endphp
                <div class="d-flex align-items-center mb-2 p-2 rounded" style="background: {{ $passed ? '#f0fdf4' : '#fff1f2' }}">
                    <i class="bi {{ $passed ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-2"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size:.9rem">{{ $attempt->quiz->title ?? 'N/A' }}</div>
                        <div class="text-muted" style="font-size:.76rem">{{ $attempt->completed_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <span class="badge {{ $passed ? 'bg-success' : 'bg-danger' }}">{{ $attempt->score }}%</span>
                </div>
                @empty
                <p class="text-muted">Bạn chưa làm quiz nào. Hãy thử sức ngay!</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
