@extends('layouts.app')
@section('title', 'Quizzes')
@section('page-title', 'Quiz')

@section('content')
<div class="section-header">
    <div>
        <h2>Danh sách Quiz</h2>
        <p class="subtitle">{{ $quizzes->total() }} quiz trong hệ thống</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm quiz...">
            </div>
            <select name="lesson_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">Tất cả bài học</option>
                @foreach($lessons as $lesson)
                    <option value="{{ $lesson->id }}" {{ request('lesson_id') == $lesson->id ? 'selected' : '' }}>
                        {{ $lesson->course->title ?? '' }} — {{ $lesson->title }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Xuất bản</option>
                <option value="draft"     {{ request('status') === 'draft'     ? 'selected' : '' }}>Bản nháp</option>
            </select>
            <button type="submit" class="btn-primary-linguist"><i class="bi bi-funnel"></i> Lọc</button>
            @if(request()->hasAny(['search','lesson_id','status']))
                <a href="{{ route('admin.quizzes.index') }}" class="btn-outline-linguist">
                    <i class="bi bi-x-lg"></i> Xóa lọc
                </a>
            @endif
        </form>
        <a href="{{ route('admin.quizzes.create') }}" class="btn-primary-linguist">
            <i class="bi bi-plus-lg"></i> THÊM QUIZ
        </a>
    </div>
</div>

@if($quizzes->isEmpty())
    <div class="empty-state">
        <i class="bi bi-patch-question"></i>
        <h5>Chưa có quiz nào</h5>
        <p>Tạo quiz đầu tiên cho bài học của bạn.</p>
        <a href="{{ route('admin.quizzes.create') }}" class="btn-primary-linguist">
            <i class="bi bi-plus-lg"></i> Tạo quiz ngay
        </a>
    </div>
@else
    <div class="table-linguist mb-4">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tiêu đề quiz</th>
                    <th>Bài học</th>
                    <th>Câu hỏi</th>
                    <th>Điểm đạt</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quizzes as $quiz)
                <tr>
                    <td class="text-muted">{{ $quizzes->firstItem() + $loop->index }}</td>
                    <td>
                        <a href="{{ route('admin.quizzes.show', $quiz) }}" style="color:var(--text-dark); font-weight:600; text-decoration:none;">
                            {{ $quiz->title }}
                        </a>
                    </td>
                    <td>
                        <div style="font-size:.82rem;">
                            <div class="text-muted">{{ $quiz->lesson->course->title ?? '—' }}</div>
                            <div>{{ $quiz->lesson->title ?? '—' }}</div>
                        </div>
                    </td>
                    <td>
                        <span class="badge rounded-pill" style="background:var(--primary-light); color:var(--primary-dark); font-weight:700;">
                            {{ $quiz->questions_count }} câu
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:700; color:{{ $quiz->passing_score >= 70 ? 'var(--success)' : 'var(--warning)' }}">
                            {{ $quiz->passing_score }}%
                        </span>
                    </td>
                    <td>
                        <span class="status-pill status-{{ $quiz->status }}">
                            <i class="bi bi-{{ $quiz->status === 'published' ? 'check-circle' : 'pencil' }}"></i>
                            {{ $quiz->status === 'published' ? 'Xuất bản' : 'Nháp' }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('admin.quizzes.show', $quiz) }}" class="btn btn-sm btn-outline-primary" title="Xem">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn btn-sm btn-outline-secondary" title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.quizzes.destroy', $quiz) }}" method="POST"
                                  onsubmit="return confirm('Xóa quiz này? Tất cả câu hỏi sẽ bị xóa!')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center">
        {{ $quizzes->links() }}
    </div>
@endif
@endsection

