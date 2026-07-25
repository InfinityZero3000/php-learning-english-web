@extends('layouts.app')
@section('title', isset($vocabulary) ? 'Sửa Từ Vựng' : 'Tạo Từ Vựng')

@section('content')
<div class="breadcrumb-linguist">
    <a href="{{ route('admin.vocabularies.index') }}">Từ vựng</a>
    <span class="sep">/</span>
    <span>{{ isset($vocabulary) ? 'Sửa' : 'Tạo mới' }}</span>
</div>

<div class="form-card" style="max-width: 800px; margin: 0 auto;">
    <h3 class="fw-bold mb-4">{{ isset($vocabulary) ? 'Sửa Từ Vựng' : 'Tạo Từ Vựng Mới' }}</h3>
    
    <form action="{{ isset($vocabulary) ? route('admin.vocabularies.update', $vocabulary) : route('admin.vocabularies.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if(isset($vocabulary)) @method('PUT') @endif
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Từ vựng (Word) <span class="text-danger">*</span></label>
                <input type="text" name="word" class="form-control @error('word') is-invalid @enderror" value="{{ old('word', $vocabulary->word ?? '') }}" required>
                @error('word')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Nghĩa (Meaning) <span class="text-danger">*</span></label>
                <input type="text" name="meaning" class="form-control @error('meaning') is-invalid @enderror" value="{{ old('meaning', $vocabulary->meaning ?? '') }}" required>
                @error('meaning')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Câu ví dụ (Example)</label>
            <textarea name="example" class="form-control" rows="2">{{ old('example', $vocabulary->example ?? '') }}</textarea>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Thuộc Bài học</label>
                <select name="lesson_id" class="form-select">
                    <option value="">-- Chọn bài học (tùy chọn) --</option>
                    @foreach($lessons as $lesson)
                        <option value="{{ $lesson->id }}" {{ old('lesson_id', $vocabulary->lesson_id ?? '') == $lesson->id ? 'selected' : '' }}>
                            {{ $lesson->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Thuộc Chủ đề</label>
                <select name="topic_id" class="form-select">
                    <option value="">-- Chọn chủ đề (tùy chọn) --</option>
                    @foreach($topics as $topic)
                        <option value="{{ $topic->id }}" {{ old('topic_id', $vocabulary->topic_id ?? '') == $topic->id ? 'selected' : '' }}>
                            {{ $topic->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Hình ảnh minh họa</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                @if(isset($vocabulary) && $vocabulary->image_path)
                    <div class="mt-2 text-success"><i class="bi bi-check-circle"></i> Đã có hình ảnh</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label">File phát âm (Audio)</label>
                <input type="file" name="audio" class="form-control" accept="audio/mpeg, audio/wav">
                @if(isset($vocabulary) && $vocabulary->audio_path)
                    <div class="mt-2 text-info"><i class="bi bi-check-circle"></i> Đã có audio</div>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-primary-linguist">
                <i class="bi bi-save"></i> Lưu từ vựng
            </button>
            <a href="{{ route('admin.vocabularies.index') }}" class="btn-outline-linguist">Hủy</a>
        </div>
    </form>
</div>
@endsection
