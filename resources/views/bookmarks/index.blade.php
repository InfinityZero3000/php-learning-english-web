@extends('layouts.app')
@section('title', 'Bookmarks')
@section('page-title', 'Từ Vựng Đã Lưu')

@section('content')
<div class="section-header">
    <div>
        <h2>Từ Vựng Đã Lưu</h2>
        <p class="subtitle">{{ $bookmarks->total() }} từ vựng trong danh sách của bạn</p>
    </div>
    <a href="{{ route('words.index') }}" class="btn-outline-linguist">
        <i class="bi bi-plus-lg"></i> Thêm từ vựng
    </a>
</div>

@if($bookmarks->isEmpty())
<div class="text-center py-5">
    <i class="bi bi-bookmark-heart" style="font-size:3.5rem; color:#c4b5fd; display:block; margin-bottom:1rem"></i>
    <h5 class="text-muted">Bạn chưa bookmark từ vựng nào</h5>
    <p class="text-muted">Vào trang <a href="{{ route('words.index') }}">Từ Vựng</a> và bấm ☆ để lưu từ bạn muốn ghi nhớ.</p>
</div>
@else
<div class="row g-3">
    @foreach($bookmarks as $bookmark)
    @php $vocab = $bookmark->vocabulary; @endphp
    <div class="col-md-4 col-sm-6">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:14px; overflow:hidden">
            @if($vocab->image_path)
                <img src="{{ asset('storage/' . $vocab->image_path) }}" class="card-img-top" style="height:100px; object-fit:cover">
            @else
                <div style="height:8px; background: linear-gradient(90deg,#667eea,#764ba2)"></div>
            @endif

            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1" style="color:#4f46e5">{{ $vocab->word }}</h5>
                        <p class="text-muted small mb-2">{{ $vocab->meaning }}</p>
                        @if($vocab->topic)
                            <span class="badge" style="background:#ede9fe; color:#6d28d9; font-size:.72rem">{{ $vocab->topic->name }}</span>
                        @endif
                    </div>
                    {{-- Xóa bookmark --}}
                    <form action="{{ route('bookmarks.destroy', $bookmark) }}" method="POST" onsubmit="return confirm('Xóa bookmark từ này?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm border-0 p-1" title="Bỏ bookmark" style="background:none; font-size:1.2rem; color:#ef4444; line-height:1">🔖</button>
                    </form>
                </div>

                @if($vocab->example)
                    <p class="fst-italic text-secondary mt-2 mb-0" style="font-size:.82rem">"{{ $vocab->example }}"</p>
                @endif

                @if($vocab->audio_path)
                    <audio controls class="w-100 mt-2" style="height:28px">
                        <source src="{{ asset('storage/' . $vocab->audio_path) }}" type="audio/mpeg">
                    </audio>
                @endif

                <div class="text-muted mt-2" style="font-size:.74rem">
                    <i class="bi bi-clock me-1"></i>Lưu lúc {{ $bookmark->created_at->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="mt-4 d-flex justify-content-center">{{ $bookmarks->links() }}</div>
@endif
@endsection
