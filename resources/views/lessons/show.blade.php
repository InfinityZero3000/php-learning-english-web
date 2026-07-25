@extends('layouts.app')
@section('title', $lesson->title)
@section('page-title', 'Chi tiết bài học')

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.lessons.index') }}">Lessons</a>
    <span class="sep"><i class="bi bi-chevron-right"></i></span>
    <span>{{ $lesson->title }}</span>
</div>

<div class="row g-4">
    {{-- LEFT: Lesson info --}}
    <div class="col-lg-8">
        <div class="form-card mb-4">
            <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <div style="font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); font-weight:600; margin-bottom:4px;">
                        {{ $lesson->course->title ?? '—' }}
                    </div>
                    <h3 style="font-weight:800; margin:0;">{{ $lesson->title }}</h3>
                </div>
                <div class="d-flex gap-2">
                    <span class="status-pill status-{{ $lesson->status }}">
                        <i class="bi bi-{{ $lesson->status === 'published' ? 'check-circle' : 'pencil' }}"></i>
                        {{ $lesson->status === 'published' ? 'Xuất bản' : 'Bản nháp' }}
                    </span>
                </div>
            </div>

            {{-- Content --}}
            @if($lesson->content)
                <div style="line-height:1.75; color:var(--text-dark);">
                    {!! nl2br(e($lesson->content)) !!}
                </div>
            @else
                <div class="empty-state" style="padding:30px 0;">
                    <i class="bi bi-file-text" style="font-size:2rem;"></i>
                    <p class="mb-0">Chưa có nội dung.</p>
                </div>
            @endif
        </div>

        {{-- Quizzes in this lesson --}}
        <div class="form-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 style="font-weight:700; margin:0;">
                    <i class="bi bi-patch-question-fill me-2" style="color:var(--primary);"></i>
                    Quiz trong bài học ({{ $lesson->quizzes->count() }})
                </h5>
                <a href="{{ route('admin.quizzes.create', ['lesson_id' => $lesson->id]) }}"
                   class="btn-primary-linguist">
                    <i class="bi bi-plus-lg"></i> Thêm quiz
                </a>
            </div>

            @if($lesson->quizzes->isEmpty())
                <div class="empty-state" style="padding:24px 0;">
                    <i class="bi bi-patch-question" style="font-size:2rem;"></i>
                    <p class="mb-0">Bài học này chưa có quiz nào.</p>
                </div>
            @else
                <div class="table-linguist">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tiêu đề quiz</th>
                                <th>Điểm đạt</th>
                                <th>Câu hỏi</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lesson->quizzes as $quiz)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td><strong>{{ $quiz->title }}</strong></td>
                                <td>{{ $quiz->passing_score }}%</td>
                                <td>
                                    <span class="badge bg-primary rounded-pill">
                                        {{ $quiz->questions->count() }} câu
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill status-{{ $quiz->status }}">
                                        {{ $quiz->status === 'published' ? 'Xuất bản' : 'Nháp' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.quizzes.show', $quiz) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.quizzes.destroy', $quiz) }}" method="POST"
                                              onsubmit="return confirm('Xóa quiz này?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
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
            @endif
        </div>
    </div>

    {{-- RIGHT: Meta panel --}}
    <div class="col-lg-4">
        <div class="form-card mb-3">
            <h6 style="font-weight:700; color:var(--text-muted); text-transform:uppercase; font-size:.75rem; letter-spacing:.5px; margin-bottom:16px;">
                Thông tin
            </h6>
            <dl class="mb-0" style="font-size:.88rem;">
                <dt class="text-muted mb-1">Khóa học</dt>
                <dd class="mb-3 fw-600">{{ $lesson->course->title ?? '—' }}</dd>

                <dt class="text-muted mb-1">Thứ tự</dt>
                <dd class="mb-3">{{ $lesson->sort_order }}</dd>

                <dt class="text-muted mb-1">Slug</dt>
                <dd class="mb-3 font-monospace text-muted" style="font-size:.78rem;">{{ $lesson->slug }}</dd>

                <dt class="text-muted mb-1">Ngày tạo</dt>
                <dd class="mb-3">{{ $lesson->created_at->format('d/m/Y H:i') }}</dd>

                <dt class="text-muted mb-1">Cập nhật lần cuối</dt>
                <dd class="mb-0">{{ $lesson->updated_at->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>

        {{-- Action panel --}}
        <div class="form-card">
            <h6 style="font-weight:700; color:var(--text-muted); text-transform:uppercase; font-size:.75rem; letter-spacing:.5px; margin-bottom:16px;">
                Thao tác
            </h6>
            <div class="d-grid gap-2">
                @can('is-admin')
                <a href="{{ route('admin.lessons.edit', $lesson) }}" class="btn-primary-linguist" style="justify-content:center;">
                    <i class="bi bi-pencil"></i> Chỉnh sửa bài học
                </a>
                <form action="{{ route('admin.lessons.destroy', $lesson) }}" method="POST"
                      onsubmit="return confirm('Xóa bài học này? Tất cả quiz bên trong cũng sẽ bị xóa!')">
                    @csrf @method('DELETE')
                    <button class="btn-danger-sm w-100" style="justify-content:center; padding:9px;">
                        <i class="bi bi-trash"></i> Xóa bài học
                    </button>
                </form>
                @else
                {{-- Nút dành cho Học viên --}}
                @auth
                <form action="{{ route('lessons.complete', $lesson) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-primary-linguist w-100" style="justify-content:center; background:linear-gradient(135deg,#10b981,#059669)">
                        <i class="bi bi-check2-circle"></i> Đánh dấu Hoàn thành
                    </button>
                </form>
                @endauth
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection

