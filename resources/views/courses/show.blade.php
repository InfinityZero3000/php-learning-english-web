@extends('layouts.app')
@section('title', $course->title)

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.courses.index') }}">Khóa học</a>
    <span class="sep">/</span>
    <span>Chi tiết</span>
</div>

<div class="section-header">
    <div>
        <h2>{{ $course->title }}</h2>
        <p class="subtitle">
            Cấp độ: <span class="badge bg-info text-dark">{{ $course->level->name ?? 'N/A' }}</span> | 
            Trạng thái: <span class="status-pill status-{{ $course->status }}">{{ $course->status }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.courses.edit', $course) }}" class="btn-primary-linguist">
            <i class="bi bi-pencil"></i> Sửa Khóa Học
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card-linguist h-100">
            <div class="card-body-linguist p-4">
                <h5 class="fw-bold mb-3">Thông tin</h5>
                <p><strong>Mô tả:</strong><br/> {{ $course->description ?: 'Chưa có mô tả.' }}</p>
                <hr/>
                <p><strong>Chủ đề:</strong><br/>
                    @forelse($course->topics as $topic)
                        <span class="badge bg-secondary">{{ $topic->name }}</span>
                    @empty
                        <span class="text-muted">Chưa gán chủ đề</span>
                    @endforelse
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card-linguist h-100">
            <div class="card-body-linguist p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Danh sách bài học ({{ $course->lessons->count() }})</h5>
                    <a href="{{ route('admin.lessons.create') }}" class="btn-outline-linguist py-1 px-2 text-sm">
                        <i class="bi bi-plus"></i> Thêm bài học
                    </a>
                </div>
                
                <ul class="list-group">
                    @forelse($course->lessons as $lesson)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $lesson->sort_order }}. {{ $lesson->title }}</strong>
                                <span class="ms-2 badge bg-light text-dark">{{ $lesson->status }}</span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.lessons.show', $lesson) }}" class="btn btn-sm btn-light border"><i class="bi bi-eye"></i></a>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted">Khóa học này chưa có bài học nào.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
