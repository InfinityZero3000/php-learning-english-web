@extends('layouts.app')
@section('title', 'Từ vựng')

@section('content')
<div class="section-header">
    <div>
        <h2>Quản lý Từ vựng</h2>
        <p class="subtitle">{{ $vocabularies->total() }} từ vựng trong hệ thống</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm từ vựng...">
            </div>
            <button type="submit" class="btn-primary-linguist">
                <i class="bi bi-funnel"></i> Lọc
            </button>
        </form>
        <a href="{{ route('admin.vocabularies.create') }}" class="btn-primary-linguist">
            <i class="bi bi-plus-lg"></i> THÊM TỪ VỰNG
        </a>
    </div>
</div>

<div class="table-linguist">
    <table class="table mb-0">
        <thead>
            <tr>
                <th>Từ vựng</th>
                <th>Nghĩa</th>
                <th>Bài học</th>
                <th>Chủ đề</th>
                <th>Media</th>
                <th class="text-end">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vocabularies as $vocab)
            <tr>
                <td><strong class="text-primary">{{ $vocab->word }}</strong></td>
                <td>{{ Str::limit($vocab->meaning, 50) }}</td>
                <td>{{ $vocab->lesson->title ?? '—' }}</td>
                <td>{{ $vocab->topic->name ?? '—' }}</td>
                <td>
                    @if($vocab->image_path) <i class="bi bi-image text-success" title="Có hình ảnh"></i> @endif
                    @if($vocab->audio_path) <i class="bi bi-music-note text-info" title="Có âm thanh"></i> @endif
                </td>
                <td class="text-end">
                    <div class="d-inline-flex gap-2">
                        <a href="{{ route('admin.vocabularies.edit', $vocab) }}" class="btn-primary-linguist py-1 px-2">
                            <i class="bi bi-pencil"></i> Sửa
                        </a>
                        <form action="{{ route('admin.vocabularies.destroy', $vocab) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                            @csrf @method('DELETE')
                            <button class="btn-danger-sm py-1 px-2 h-100"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có từ vựng nào.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3 d-flex justify-content-center">{{ $vocabularies->links() }}</div>
@endsection
