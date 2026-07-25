@extends('layouts.app')
@section('title', isset($course) ? 'Sửa Khóa Học' : 'Tạo Khóa Học')

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.courses.index') }}">Khóa học</a>
    <span class="sep">/</span>
    <span>{{ isset($course) ? 'Sửa' : 'Tạo mới' }}</span>
</div>

<div class="form-card" style="max-width: 800px; margin: 0 auto;">
    <h3 class="fw-bold mb-4">{{ isset($course) ? 'Sửa Khóa Học' : 'Tạo Khóa Học Mới' }}</h3>
    
    <form action="{{ isset($course) ? route('admin.courses.update', $course) : route('admin.courses.store') }}" method="POST">
        @csrf
        @if(isset($course)) @method('PUT') @endif
        
        <div class="mb-3">
            <label class="form-label">Tiêu đề khóa học <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $course->title ?? '') }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Cấp độ</label>
                <select name="level_id" class="form-select">
                    <option value="">-- Chọn cấp độ --</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->id }}" {{ old('level_id', $course->level_id ?? '') == $level->id ? 'selected' : '' }}>
                            {{ $level->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="draft" {{ old('status', $course->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Bản nháp</option>
                    <option value="published" {{ old('status', $course->status ?? 'draft') === 'published' ? 'selected' : '' }}>Xuất bản</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Chủ đề (Topics)</label>
            <select name="topics[]" class="form-select" multiple style="height: 120px;">
                @php $selectedTopics = isset($course) ? $course->topics->pluck('id')->toArray() : []; @endphp
                @foreach($topics as $topic)
                    <option value="{{ $topic->id }}" {{ in_array($topic->id, old('topics', $selectedTopics)) ? 'selected' : '' }}>
                        {{ $topic->name }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Giữ phím Ctrl (Windows) hoặc Cmd (Mac) để chọn nhiều chủ đề.</small>
        </div>

        <div class="mb-4">
            <label class="form-label">Mô tả</label>
            <textarea name="description" class="form-control" rows="4">{{ old('description', $course->description ?? '') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-linguist">
                <i class="bi bi-save"></i> Lưu khóa học
            </button>
            <a href="{{ route('admin.courses.index') }}" class="btn-outline-linguist">Hủy</a>
        </div>
    </form>
</div>
@endsection
