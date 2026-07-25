@extends('layouts.app')
@section('title', 'Khóa học')

@section('content')
<div class="section-header">
    <div>
        <h2>Quản lý Khóa học</h2>
        <p class="subtitle">{{ $courses->total() }} khóa học trong hệ thống</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm khóa học...">
            </div>
            <button type="submit" class="btn-primary-linguist">
                <i class="bi bi-funnel"></i> Lọc
            </button>
        </form>
        <a href="{{ route('admin.courses.create') }}" class="btn-primary-linguist">
            <i class="bi bi-plus-lg"></i> THÊM KHÓA HỌC
        </a>
    </div>
</div>

<div class="table-linguist">
    <table class="table mb-0">
        <thead>
            <tr>
                <th>Tiêu đề</th>
                <th>Cấp độ</th>
                <th>Chủ đề</th>
                <th>Trạng thái</th>
                <th class="text-end">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($courses as $course)
            <tr>
                <td>
                    <div class="fw-bold">{{ $course->title }}</div>
                    <small class="text-muted">{{ $course->lessons->count() ?? 0 }} bài học</small>
                </td>
                <td>{{ $course->level->name ?? '—' }}</td>
                <td>
                    @foreach($course->topics as $topic)
                        <span class="badge bg-secondary">{{ $topic->name }}</span>
                    @endforeach
                </td>
                <td>
                    <span class="status-pill status-{{ $course->status }}">
                        {{ $course->status === 'published' ? 'Xuất bản' : 'Nháp' }}
                    </span>
                </td>
                <td class="text-end">
                    <div class="d-inline-flex gap-2">
                        <a href="{{ route('admin.courses.edit', $course) }}" class="btn-primary-linguist py-1 px-2">
                            <i class="bi bi-pencil"></i> Sửa
                        </a>
                        <form action="{{ route('admin.courses.destroy', $course) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                            @csrf @method('DELETE')
                            <button class="btn-danger-sm py-1 px-2 h-100"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center py-4 text-muted">Chưa có khóa học nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3 d-flex justify-content-center">{{ $courses->links() }}</div>
@endsection
