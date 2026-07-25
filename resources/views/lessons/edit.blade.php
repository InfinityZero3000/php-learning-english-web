@extends('layouts.app')
@section('title', 'Sửa: ' . $lesson->title)
@section('page-title', 'Chỉnh sửa bài học')

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.lessons.index') }}">Lessons</a>
    <span class="sep"><i class="bi bi-chevron-right"></i></span>
    <a href="{{ route('admin.lessons.show', $lesson) }}">{{ Str::limit($lesson->title, 40) }}</a>
    <span class="sep"><i class="bi bi-chevron-right"></i></span>
    <span>Chỉnh sửa</span>
</div>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="form-card">
            <h4 class="mb-1" style="font-weight:700;">
                <i class="bi bi-pencil-square me-2" style="color:var(--primary);"></i>
                Chỉnh sửa bài học
            </h4>
            <p class="text-muted mb-4" style="font-size:.87rem;">
                Cập nhật thông tin cho bài học <strong>{{ $lesson->title }}</strong>.
            </p>

            <form action="{{ route('admin.lessons.update', $lesson) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Course --}}
                <div class="mb-4">
                    <label for="course_id" class="form-label">
                        Khóa học <span class="text-danger">*</span>
                    </label>
                    <select name="course_id" id="course_id"
                            class="form-select @error('course_id') is-invalid @enderror">
                        <option value="">— Chọn khóa học —</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}"
                                {{ old('course_id', $lesson->course_id) == $course->id ? 'selected' : '' }}>
                                {{ $course->title }}
                                @if($course->level) ({{ $course->level->name }}) @endif
                            </option>
                        @endforeach
                    </select>
                    @error('course_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Title --}}
                <div class="mb-4">
                    <label for="title" class="form-label">
                        Tiêu đề bài học <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="title" id="title"
                           class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $lesson->title) }}">
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Content --}}
                <div class="mb-4">
                    <label for="content" class="form-label">Nội dung bài học</label>
                    <textarea name="content" id="content" rows="10"
                              class="form-control @error('content') is-invalid @enderror">{{ old('content', $lesson->content) }}</textarea>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-4">
                    {{-- Sort order --}}
                    <div class="col-sm-6">
                        <label for="sort_order" class="form-label">Thứ tự hiển thị</label>
                        <input type="number" name="sort_order" id="sort_order"
                               class="form-control @error('sort_order') is-invalid @enderror"
                               value="{{ old('sort_order', $lesson->sort_order) }}" min="0">
                        @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div class="col-sm-6">
                        <label for="status" class="form-label">
                            Trạng thái <span class="text-danger">*</span>
                        </label>
                        <select name="status" id="status"
                                class="form-select @error('status') is-invalid @enderror">
                            <option value="draft"     {{ old('status', $lesson->status) === 'draft'     ? 'selected' : '' }}>📝 Bản nháp</option>
                            <option value="published" {{ old('status', $lesson->status) === 'published' ? 'selected' : '' }}>✅ Xuất bản</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex gap-3 justify-content-end">
                    <a href="{{ route('admin.lessons.show', $lesson) }}" class="btn-outline-linguist">
                        <i class="bi bi-x-lg"></i> Hủy
                    </a>
                    <button type="submit" class="btn-primary-linguist">
                        <i class="bi bi-floppy"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

