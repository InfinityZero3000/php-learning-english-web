@extends('layouts.app')
@section('title', 'Từ Vựng')
@section('page-title', 'Kho Từ Vựng')

@section('content')
<div class="section-header">
    <div>
        <h2>Kho Từ Vựng</h2>
        <p class="subtitle">{{ $vocabularies->total() }} từ vựng · Bấm ☆ để bookmark từ bạn muốn ghi nhớ</p>
    </div>
</div>

{{-- Bộ lọc --}}
<form method="GET" class="d-flex flex-wrap gap-2 mb-4">
    <div class="search-bar flex-grow-1" style="max-width:320px">
        <i class="bi bi-search"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm từ vựng hoặc nghĩa...">
    </div>
    <select name="topic_id" class="form-select" style="max-width:200px">
        <option value="">Tất cả chủ đề</option>
        @foreach($topics as $topic)
            <option value="{{ $topic->id }}" {{ request('topic_id') == $topic->id ? 'selected' : '' }}>{{ $topic->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-primary-linguist"><i class="bi bi-funnel"></i> Lọc</button>
    @if(request('search') || request('topic_id'))
        <a href="{{ route('words.index') }}" class="btn-outline-linguist">Xóa lọc</a>
    @endif
</form>

{{-- Lưới từ vựng --}}
<div class="row g-3">
    @forelse($vocabularies as $vocab)
    <div class="col-md-4 col-sm-6">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:14px; overflow:hidden; transition: transform .15s; cursor:pointer"
             onmouseenter="this.style.transform='translateY(-3px)'" onmouseleave="this.style.transform='translateY(0)'">

            {{-- Hình ảnh nếu có --}}
            @if($vocab->image_path)
                <img src="{{ asset('storage/' . $vocab->image_path) }}" class="card-img-top" style="height:130px; object-fit:cover">
            @else
                <div style="height:60px; background: linear-gradient(135deg,#667eea,#764ba2)"></div>
            @endif

            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1" style="color:#4f46e5">{{ $vocab->word }}</h5>
                        <p class="text-muted small mb-2">{{ Str::limit($vocab->meaning, 60) }}</p>
                        @if($vocab->topic)
                            <span class="badge" style="background:#ede9fe; color:#6d28d9; font-size:.72rem">{{ $vocab->topic->name }}</span>
                        @endif
                    </div>
                    {{-- Bookmark Button --}}
                    @auth
                    <form action="{{ route('bookmarks.toggle', $vocab) }}" method="POST" class="ms-2">
                        @csrf
                        <button type="submit" class="btn btn-sm border-0 p-1"
                                title="{{ in_array($vocab->id, $bookmarkedIds) ? 'Bỏ bookmark' : 'Thêm bookmark' }}"
                                style="background:none; font-size:1.3rem; line-height:1">
                            {{ in_array($vocab->id, $bookmarkedIds) ? '🔖' : '☆' }}
                        </button>
                    </form>
                    @endauth
                </div>

                @if($vocab->example)
                    <p class="mt-2 mb-0 fst-italic text-secondary" style="font-size:.82rem">
                        "{{ Str::limit($vocab->example, 70) }}"
                    </p>
                @endif

                @if($vocab->audio_path)
                    <audio controls class="w-100 mt-2" style="height:28px">
                        <source src="{{ asset('storage/' . $vocab->audio_path) }}" type="audio/mpeg">
                    </audio>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-search fs-1 d-block mb-2"></i>
        Không tìm thấy từ vựng nào.
    </div>
    @endforelse
</div>

<div class="mt-4 d-flex justify-content-center">{{ $vocabularies->links() }}</div>
@endsection
