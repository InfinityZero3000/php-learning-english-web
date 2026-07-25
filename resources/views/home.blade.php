@extends('layouts.app')

@section('title', 'Website học tiếng Anh')
@section('top', 'Trang chủ')
@section('subtitle', 'Khám phá lộ trình học tiếng Anh')

@section('content')
    <div class="hero">
        <h1>Chào mừng bạn đến với LexiLingo</h1>
        <p>Học từ vựng, làm quiz và theo dõi tiến trình học tập với trải nghiệm trực quan và hiện đại.</p>
    </div>

    <div class="wrap">
        <div class="grid-2">
            <div class="card">
                <div class="metric">MỤC TIÊU HÔM NAY</div>
                <div class="number">3 bài học</div>
                <p class="metric" style="margin-top:8px">Dành 20 phút để củng cố từ vựng và hoàn thành một quiz ngắn.</p>
            </div>
            <div class="card">
                <div class="metric">LỘ TRÌNH ĐỀ XUẤT</div>
                <div class="number">A2 → B1</div>
                <p class="metric" style="margin-top:8px">Tập trung vào ngữ pháp nền tảng và câu giao tiếp hàng ngày.</p>
            </div>
        </div>

        <div class="card" style="margin-top:16px">
            <div class="toolbar">
                <strong>Tiện ích nổi bật</strong>
                <a class="link" href="{{ route('content.index') }}">Quản lý nội dung</a>
            </div>
            <div class="grid">
                <div class="card" style="padding:16px;box-shadow:none">
                    <div class="metric">QUIZ</div>
                    <div class="number">12</div>
                    <p class="metric">Bài quiz sẵn sàng cho bạn luyện tập hằng ngày.</p>
                </div>
                <div class="card" style="padding:16px;box-shadow:none">
                    <div class="metric">TIẾN ĐỘ</div>
                    <div class="number">87%</div>
                    <p class="metric">Theo dõi mức độ hoàn thành và duy trì streak.</p>
                </div>
                <div class="card" style="padding:16px;box-shadow:none">
                    <div class="metric">TỪ VỰNG</div>
                    <div class="number">240+</div>
                    <p class="metric">Kho từ vựng được tổ chức theo chủ đề và cấp độ.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
