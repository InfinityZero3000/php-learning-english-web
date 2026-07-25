@extends('layouts.app')

@section('title', 'Từ vựng của tôi')

@section('topbar')
<div class="topbar">
    <div class="topbar-left">
        <h1>📚 Từ vựng</h1>
        <span class="breadcrumb">/ Khám phá từ vựng</span>
    </div>
    <div class="topbar-actions">
        <span style="font-size:13px;color:var(--gray);font-weight:600;">
            📋 {{ $vocabularies->total() }} từ
        </span>
        <a href="{{ route('vocabularies.import') }}" class="btn btn-outline btn-sm">
            📥 Import
        </a>
    </div>
</div>
@endsection

@section('content')
{{-- Hero Banner --}}
<div class="hero">
    <h1>Kho từ vựng</h1>
    <p>Học từ mới mỗi ngày với hình ảnh minh họa, phát âm chuẩn và ví dụ thực tế giúp bạn ghi nhớ dễ dàng hơn.</p>
    <div class="hero-stats">
        <div class="hero-stat">
            <div class="val">{{ $vocabularies->total() }}</div>
            <div class="lbl">Từ vựng</div>
        </div>
        <div class="hero-stat">
            <div class="val">{{ count($topics) }}</div>
            <div class="lbl">Chủ đề</div>
        </div>
    </div>
</div>

{{-- Toolbar: Search + Filter + Bookmark Toggle --}}
<div class="toolbar" style="flex-wrap:wrap;gap:10px;">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1;">
        <div class="search-box" style="flex:1;min-width:200px;">
            <i data-lucide="search"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm từ vựng...">
        </div>
        <select name="topic" class="form-select" style="max-width:180px;" onchange="this.form.submit()">
            <option value="">Tất cả chủ đề</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" {{ request('topic') == $topic->id ? 'selected' : '' }}>{{ $topic->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">
            <i data-lucide="filter" style="width:14px;height:14px;"></i> Lọc
        </button>
        @if(request('search') || request('topic'))
            <a href="{{ route('vocabularies.index') }}" class="btn btn-ghost btn-sm">Xóa bộ lọc</a>
        @endif
    </form>
    @auth
        @php
            $bmParams = request()->except('page');
            if (request('bookmarked')) {
                unset($bmParams['bookmarked']);
            } else {
                $bmParams['bookmarked'] = 1;
            }
        @endphp
        <a href="?{{ http_build_query($bmParams) }}" class="btn btn-sm {{ request('bookmarked') ? 'btn-primary' : 'btn-ghost' }}" style="white-space:nowrap;">
            ⭐ Đã lưu
        </a>
    @endauth
</div>

{{-- Pagination Top --}}
@if($vocabularies->hasPages())
    <div style="margin-bottom:12px;display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-size:13px;font-weight:600;">
        @if($vocabularies->onFirstPage())
            <span style="padding:6px 12px;border-radius:8px;background:var(--gray-soft);color:#94a3b8;cursor:not-allowed;">← Trước</span>
        @else
            <a href="{{ $vocabularies->appends(request()->except('page'))->previousPageUrl() }}" style="padding:6px 12px;border-radius:8px;background:var(--surface);border:1px solid var(--line);color:var(--brand);text-decoration:none;">← Trước</a>
        @endif
        <span style="color:var(--gray);">
            Trang {{ $vocabularies->currentPage() }} / {{ $vocabularies->lastPage() }} • {{ $vocabularies->total() }} từ
        </span>
        @if($vocabularies->hasMorePages())
            <a href="{{ $vocabularies->appends(request()->except('page'))->nextPageUrl() }}" style="padding:6px 12px;border-radius:8px;background:var(--surface);border:1px solid var(--line);color:var(--brand);text-decoration:none;">Sau →</a>
        @else
            <span style="padding:6px 12px;border-radius:8px;background:var(--gray-soft);color:#94a3b8;cursor:not-allowed;">Sau →</span>
        @endif
    </div>
@endif

{{-- Vocabulary Grid --}}
@if($vocabularies->isEmpty())
    <div class="empty-state">
        <i data-lucide="book-x"></i>
        <h3>Không tìm thấy từ vựng nào</h3>
        <p>Thử thay đổi từ khóa tìm kiếm hoặc chọn chủ đề khác.</p>
    </div>
@else
    <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:18px;">
        @foreach($vocabularies as $vocab)
            <div class="card vocab-card" style="overflow:hidden;padding:0;display:flex;flex-direction:column;">
                {{-- Image Section --}}
                <div style="position:relative;height:160px;background:linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);overflow:hidden;">
                    @if($vocab->image_url || $vocab->image_path)
                        @php
                            $imgSrc = $vocab->image_url ?? asset('storage/'.$vocab->image_path);
                        @endphp
                        <img src="{{ $imgSrc }}"
                             alt="{{ $vocab->word }}"
                             style="width:100%;height:100%;object-fit:cover;transition:transform 0.3s ease;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;background:linear-gradient(135deg, {{ ['#eef2ff','#ede9fe','#fce7f3','#fef3c7','#d1fae5','#dbeafe'][$loop->index % 6] }}, {{ ['#c7d2fe','#ddd6fe','#fbcfe8','#fde68a','#a7f3d0','#bfdbfe'][$loop->index % 6] }});font-size:64px;">
                            {{ ['📖','📝','💬','🎯','🌟','🔤'][$loop->index % 6] }}
                        </div>
                    @else
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, {{ ['#eef2ff','#ede9fe','#fce7f3','#fef3c7','#d1fae5','#dbeafe'][$loop->index % 6] }}, {{ ['#c7d2fe','#ddd6fe','#fbcfe8','#fde68a','#a7f3d0','#bfdbfe'][$loop->index % 6] }});font-size:64px;">
                            {{ ['📖','📝','💬','🎯','🌟','🔤'][$loop->index % 6] }}
                        </div>
                    @endif

                    {{-- Part of Speech Badge on Image --}}
                    @if($vocab->part_of_speech)
                        <span style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,0.6);color:#fff;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">
                            {{ $vocab->part_of_speech }}
                        </span>
                    @endif

                    {{-- Bookmark Button --}}
                    @auth
                        <form action="{{ route('bookmarks.toggle', $vocab) }}" method="POST" style="position:absolute;top:10px;right:10px;">
                            @csrf
                            <button type="submit" style="background:rgba(255,255,255,0.9);border:none;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;box-shadow:0 2px 6px rgba(0,0,0,0.15);transition:all 0.2s;"
                                title="{{ $vocab->isBookmarkedBy(auth()->user()) ? 'Bỏ lưu' : 'Lưu' }}">
                                <span style="color:{{ $vocab->isBookmarkedBy(auth()->user()) ? '#f59e0b' : '#94a3b8' }};">
                                    {{ $vocab->isBookmarkedBy(auth()->user()) ? '⭐' : '☆' }}
                                </span>
                            </button>
                        </form>
                    @endauth
                </div>

                {{-- Content Section --}}
                <div style="padding:18px 20px 20px;flex:1;display:flex;flex-direction:column;">
                    {{-- Word + Meaning --}}
                    <div style="margin-bottom:8px;">
                        <h3 style="font-size:22px;font-weight:900;color:var(--ink);margin:0;line-height:1.2;">
                            {{ $vocab->word }}
                        </h3>
                        @if($vocab->pronunciation)
                            <p style="font-size:13px;color:var(--brand);font-weight:600;margin:2px 0 0;">
                                /{{ $vocab->pronunciation }}/
                            </p>
                        @endif
                    </div>
                    <p style="font-size:15px;color:var(--gray);margin:0 0 6px;font-weight:600;">
                        {{ $vocab->meaning }}
                    </p>

                    {{-- Example --}}
                    @if($vocab->example)
                        <div style="background:var(--gray-soft);border-radius:var(--radius-sm);padding:10px 14px;margin:8px 0;border-left:3px solid var(--brand);">
                            <p style="font-size:13px;color:var(--ink);margin:0;font-style:italic;line-height:1.5;">
                                "{{ $vocab->example }}"
                            </p>
                        </div>
                    @endif

                    {{-- Badges Row --}}
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:auto;padding-top:12px;">
                        @if($vocab->topic)
                            <span class="badge" style="background:var(--purple-soft);color:#6d28d9;">📂 {{ $vocab->topic->name }}</span>
                        @endif
                        @if($vocab->difficulty)
                            @php
                                $diffColors = [
                                    'beginner' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                                    'easy' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                                    'intermediate' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                                    'medium' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                                    'advanced' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                                    'hard' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                                ];
                                $dc = $diffColors[strtolower($vocab->difficulty)] ?? ['bg' => 'var(--gray-soft)', 'color' => 'var(--gray)'];
                            @endphp
                            <span class="badge" style="background:{{ $dc['bg'] }};color:{{ $dc['color'] }};">
                                {{ ucfirst($vocab->difficulty) }}
                            </span>
                        @endif
                        @if($vocab->cefr_level)
                            <span class="badge" style="background:var(--blue-soft);color:var(--blue);">
                                {{ strtoupper($vocab->cefr_level) }}
                            </span>
                        @endif
                    </div>

                    {{-- Audio Play Button --}}
                    @if($vocab->audio_url || $vocab->audio_path)
                        <button onclick="playAudio('{{ $vocab->audio_url ?? asset('storage/'.$vocab->audio_path) }}')"
                            style="margin-top:10px;display:inline-flex;align-items:center;gap:6px;background:var(--brand-soft);color:var(--brand);border:none;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;cursor:pointer;">
                            🔊 Nghe phát âm
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- Pagination Bottom --}}
@if($vocabularies->hasPages())
    <div class="mt-6" style="display:flex;justify-content:center;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:4px;font-size:14px;font-weight:600;flex-wrap:wrap;justify-content:center;">
            @if($vocabularies->onFirstPage())
                <span style="padding:8px 14px;border-radius:10px;background:var(--gray-soft);color:#94a3b8;cursor:not-allowed;">← Trước</span>
            @else
                <a href="{{ $vocabularies->appends(request()->except('page'))->previousPageUrl() }}" style="padding:8px 14px;border-radius:10px;background:var(--surface);border:1px solid var(--line);color:var(--brand);text-decoration:none;">← Trước</a>
            @endif

            @php
                $currentPage = $vocabularies->currentPage();
                $lastPage = $vocabularies->lastPage();
                $start = max(1, $currentPage - 2);
                $end = min($lastPage, $currentPage + 2);

                // Always show at least 5 pages if possible
                if ($end - $start < 4) {
                    if ($start === 1) {
                        $end = min($lastPage, $start + 4);
                    } else {
                        $start = max(1, $end - 4);
                    }
                }
            @endphp

            @if($start > 1)
                <a href="{{ $vocabularies->appends(request()->except('page'))->url(1) }}" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--surface);border:1px solid var(--line);color:var(--ink);text-decoration:none;">1</a>
                @if($start > 2)
                    <span style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;color:var(--gray);">…</span>
                @endif
            @endif

            @for($page = $start; $page <= $end; $page++)
                @if($page == $currentPage)
                    <span style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--brand);color:#fff;font-weight:800;">{{ $page }}</span>
                @else
                    <a href="{{ $vocabularies->appends(request()->except('page'))->url($page) }}" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--surface);border:1px solid var(--line);color:var(--ink);text-decoration:none;">{{ $page }}</a>
                @endif
            @endfor

            @if($end < $lastPage)
                @if($end < $lastPage - 1)
                    <span style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;color:var(--gray);">…</span>
                @endif
                <a href="{{ $vocabularies->appends(request()->except('page'))->url($lastPage) }}" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--surface);border:1px solid var(--line);color:var(--ink);text-decoration:none;">{{ $lastPage }}</a>
            @endif

            @if($vocabularies->hasMorePages())
                <a href="{{ $vocabularies->appends(request()->except('page'))->nextPageUrl() }}" style="padding:8px 14px;border-radius:10px;background:var(--surface);border:1px solid var(--line);color:var(--brand);text-decoration:none;">Sau →</a>
            @else
                <span style="padding:8px 14px;border-radius:10px;background:var(--gray-soft);color:#94a3b8;cursor:not-allowed;">Sau →</span>
            @endif
        </div>
    </div>
    <div style="text-align:center;margin-top:8px;font-size:12px;color:var(--gray);font-weight:600;">
        Hiển thị {{ $vocabularies->firstItem() }}–{{ $vocabularies->lastItem() }} trên tổng {{ $vocabularies->total() }} từ
    </div>
@endif
@endsection

@push('scripts')
<script>
    lucide.createIcons();
    function playAudio(url) {
        new Audio(url).play().catch(e => console.log('Audio play failed:', e));
    }
</script>
@endpush