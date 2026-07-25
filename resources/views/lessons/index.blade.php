@extends('layouts.app')
@section('title', 'Lessons')
@section('page-title', 'Lessons')

@section('content')
{{-- Stat row --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-book-half"></i></div>
            <div>
                <div class="stat-value">{{ $lessons->total() }}</div>
                <div class="stat-label">Tổng bài học</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-value">{{ $lessons->getCollection()->where('status','published')->count() }}</div>
                <div class="stat-label">Đã xuất bản</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="bi bi-pencil-square"></i></div>
            <div>
                <div class="stat-value">{{ $lessons->getCollection()->where('status','draft')->count() }}</div>
                <div class="stat-label">Bản nháp</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-journals"></i></div>
            <div>
                <div class="stat-value">{{ $courses->count() }}</div>
                <div class="stat-label">Khóa học</div>
            </div>
        </div>
    </div>
</div>

{{-- Section header --}}
<div class="section-header">
    <div>
        <h2>My Lessons</h2>
        <p class="subtitle">{{ $lessons->total() }} bài học trong hệ thống</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- Search --}}
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm bài học...">
            </div>

            {{-- Filter course --}}
            <select name="course_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">Tất cả khóa học</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" {{ request('course_id') == $course->id ? 'selected' : '' }}>
                        {{ $course->title }}
                    </option>
                @endforeach
            </select>

            {{-- Filter status --}}
            <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Đã xuất bản</option>
                <option value="draft"     {{ request('status') === 'draft'     ? 'selected' : '' }}>Bản nháp</option>
            </select>

            <button type="submit" class="btn-primary-linguist">
                <i class="bi bi-funnel"></i> Lọc
            </button>

            @if(request()->hasAny(['search','course_id','status']))
                <a href="{{ route('admin.lessons.index') }}" class="btn-outline-linguist">
                    <i class="bi bi-x-lg"></i> Xóa lọc
                </a>
            @endif
        </form>

        <a href="{{ route('admin.lessons.create') }}" class="btn-primary-linguist">
            <i class="bi bi-plus-lg"></i> THÊM BÀI HỌC
        </a>
    </div>
</div>

{{-- Lessons grid --}}
@if($lessons->isEmpty())
    <div class="empty-state">
        <i class="bi bi-book"></i>
        <h5>Chưa có bài học nào</h5>
        <p>Bắt đầu bằng cách tạo bài học đầu tiên.</p>
        <a href="{{ route('admin.lessons.create') }}" class="btn-primary-linguist">
            <i class="bi bi-plus-lg"></i> Tạo bài học mới
        </a>
    </div>
@else
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
        @foreach($lessons as $lesson)
        <div class="col">
            <div class="card-linguist h-100">
                {{-- Card thumbnail --}}
                <div class="card-thumb">
                    <i class="bi bi-journal-text" style="font-size:3rem; color: var(--primary); opacity:.4;"></i>
                </div>

                <div class="card-body-linguist">
                    {{-- Course label --}}
                    <div style="font-size:.72rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px;">
                        {{ $lesson->course->title ?? '—' }}
                    </div>

                    {{-- Title + status --}}
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div class="card-title-sm">{{ $lesson->title }}</div>
                        <span class="status-pill status-{{ $lesson->status }} flex-shrink-0">
                            <i class="bi bi-{{ $lesson->status === 'published' ? 'check-circle' : 'pencil' }}"></i>
                            {{ $lesson->status === 'published' ? 'Xuất bản' : 'Nháp' }}
                        </span>
                    </div>

                    {{-- Content preview --}}
                    @if($lesson->content)
                        <p style="font-size:.82rem; color:var(--text-muted); margin-bottom:10px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                            {{ strip_tags($lesson->content) }}
                        </p>
                    @endif

                    {{-- Meta --}}
                    <div class="d-flex gap-3 mb-12" style="font-size:.76rem; color:var(--text-muted);">
                        <span><i class="bi bi-sort-numeric-up me-1"></i>Thứ tự: {{ $lesson->sort_order }}</span>
                    </div>

                    {{-- Action buttons --}}
                    <div class="d-flex gap-2 mt-3">
                        <a href="{{ route('admin.lessons.show', $lesson) }}" class="btn-outline-linguist flex-grow-1" style="justify-content:center;">
                            <i class="bi bi-eye"></i> Xem
                        </a>
                        <a href="{{ route('admin.lessons.edit', $lesson) }}" class="btn-primary-linguist flex-grow-1" style="justify-content:center;">
                            <i class="bi bi-pencil"></i> Sửa
                        </a>
                        <form action="{{ route('admin.lessons.destroy', $lesson) }}" method="POST"
                              onsubmit="return confirm('Xóa bài học này? Tất cả quiz liên quan cũng sẽ bị xóa!')">
                            @csrf @method('DELETE')
                            <button class="btn-danger-sm h-100">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    <div class="d-flex justify-content-center">
        {{ $lessons->links() }}
    </div>
@endif
@endsection

