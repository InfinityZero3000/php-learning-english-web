@extends('layouts.app')

@section('title', 'Từ vựng đã lưu - Linguist')
@section('top', 'Từ vựng đã lưu')
@section('subtitle', 'Danh sách từ vựng bạn đã bookmark')

@section('content')
<div class="wrap">
    <div class="toolbar">
        <form method="GET" style="display:flex; gap:10px; flex:1;">
            <input type="text" name="search" placeholder="Tìm từ đã lưu..." value="{{ request('search') }}" style="max-width:340px;">
            <button type="submit" class="btn"><i data-lucide="search" style="width:16px;height:16px;"></i> Tìm</button>
        </form>
        <a href="{{ route('vocabularies.index') }}" class="btn white"><i data-lucide="book-open" style="width:16px;height:16px;"></i> Tất cả từ vựng</a>
    </div>

    @if(session('success'))
        <div style="background:#dcfce7;color:#166534;padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:700;">{{ session('success') }}</div>
    @endif

    @if($vocabularies->isEmpty())
        <div class="card" style="text-align:center; padding:60px 20px;">
            <i data-lucide="bookmark" style="width:48px;height:48px;color:#b0c4ce;margin-bottom:12px;"></i>
            <h3 style="color:#637b87;">Chưa có từ vựng nào được lưu</h3>
            <p style="color:#8ba0ab;margin-bottom:18px;">Bookmark từ vựng yêu thích để xem lại sau.</p>
            <a href="{{ route('vocabularies.index') }}" class="btn">Khám phá từ vựng</a>
        </div>
    @else
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Từ</th>
                        <th>Nghĩa</th>
                        <th>Phát âm</th>
                        <th>Ví dụ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vocabularies as $vocab)
                    <tr>
                        <td>
                            <strong style="font-size:16px;color:var(--brand);">{{ $vocab->word }}</strong>
                            @if($vocab->level)
                                <span class="badge {{ strtolower($vocab->level->name) }}">{{ $vocab->level->name }}</span>
                            @endif
                        </td>
                        <td>{{ $vocab->meaning }}</td>
                        <td style="color:#637b87;">{{ $vocab->pronunciation ?? '—' }}</td>
                        <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $vocab->example ?? '—' }}</td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                @if($vocab->audio_url)
                                    <button onclick="new Audio('{{ $vocab->audio_url }}').play()" class="btn white" style="padding:6px 10px;" title="Nghe"><i data-lucide="volume-2" style="width:14px;height:14px;"></i></button>
                                @endif
                                <form action="{{ route('bookmarks.toggle', $vocab) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn" style="background:#f59e0b;padding:6px 10px;" title="Bỏ bookmark"><i data-lucide="bookmark-minus" style="width:14px;height:14px;"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px;">
            {{ $vocabularies->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection