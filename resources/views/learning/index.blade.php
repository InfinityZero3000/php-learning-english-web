@extends('layouts.app')
@section('title','Học tập')
@section('top','Progress')
@section('subtitle','Theo dõi hành trình học tập của bạn')

@section('content')
<div class="hero">
    <h1>Tiến độ · Bookmark · Lịch sử học</h1>
    <p>Theo dõi hành trình học tiếng Anh của bạn với một bảng điều khiển dễ nhìn.</p>
</div>

<div class="wrap">
    <div class="tabs">
        <button class="tab active" onclick="showTab('dash', this)">Dashboard</button>
        <button class="tab" onclick="showTab('book', this)">Bookmark</button>
        <button class="tab" onclick="showTab('history', this)">Lịch sử học</button>
    </div>

    <section id="dash">
        <div class="four">
            <div class="card">
                <div class="metric">STREAK NGÀY</div>
                <div class="number">0</div>
            </div>
            <div class="card">
                <div class="metric">ĐIỂM QUIZ TB 30 NGÀY</div>
                <div class="number">0</div>
            </div>
            <div class="card">
                <div class="metric">BÀI HỌC HOÀN THÀNH</div>
                <div class="number">0/0</div>
            </div>
            <div class="card">
                <div class="metric">TỪ VỰNG ĐÃ LƯU</div>
                <div class="number">{{ $bookmarkCount }}</div>
            </div>
        </div>

        <div class="card" style="margin-top:16px">
            <h3 style="margin-top:0">Phút học mỗi ngày (7 ngày qua)</h3>
            <div style="height:160px;display:flex;align-items:end;gap:18px;padding:20px 0">
                @foreach(range(1, 7) as $d)
                    <div style="flex:1;text-align:center">
                        <div style="height:{{ 20 + $d * 10 }}px;background:linear-gradient(180deg,var(--brand),var(--brand-dark));border-radius:10px 10px 0 0"></div>
                        <small>T{{ $d }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="book" style="display:none">
        <div class="card">
            <h3 style="margin-top:0">🔖 Từ vựng đã bookmark</h3>
            <p class="metric">Chưa có từ vựng nào được bookmark. Khám phá trang Words để lưu từ mới.</p>
            <button class="btn">Khám phá từ vựng</button>
        </div>
    </section>

    <section id="history" style="display:none">
        <div class="card">
            <h3 style="margin-top:0">Lịch sử học</h3>
            @forelse($history as $item)
                <p>📘 <b>{{ ucfirst($item->activity_type) }}</b> <span class="metric">· {{ $item->created_at }}</span></p>
            @empty
                <p class="metric">Không có hoạt động nào.</p>
            @endforelse
        </div>
    </section>
</div>

<script>
    function showTab(id, b) {
        ['dash', 'book', 'history'].forEach(x => document.getElementById(x).style.display = x == id ? 'block' : 'none');
        document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
    }
</script>
@endsection
