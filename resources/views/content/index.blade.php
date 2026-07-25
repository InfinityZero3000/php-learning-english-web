@extends('layouts.app')
@section('title','Quản lý nội dung')
@section('top','Content management')
@section('subtitle','Quản lý bài học, quiz và câu hỏi')

@section('content')
<div class="hero">
    <h1>Quản lý nội dung</h1>
    <p>Tạo bài học, quiz và câu hỏi cho học viên với giao diện trực quan.</p>
</div>

<div class="wrap">
    <div class="grid-2" style="margin-bottom:16px">
        <div class="card">
            <div class="metric">TỔNG BÀI HỌC</div>
            <div class="number">{{ count($lessons) }}</div>
        </div>
        <div class="card">
            <div class="metric">TRẠNG THÁI XUẤT BẢN</div>
            <div class="number">{{ $lessons->where('is_published', true)->count() }}/{{ count($lessons) }}</div>
        </div>
    </div>

    <div class="toolbar">
        <div class="crumb">Bài học</div>
        <button class="btn" onclick="modal('lessonModal')">＋ Thêm bài học</button>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>BÀI HỌC</th>
                    <th>CẤP ĐỘ</th>
                    <th>CEFR</th>
                    <th>TRẠNG THÁI</th>
                    <th>QUIZ</th>
                    <th>HÀNH ĐỘNG</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lessons as $lesson)
                    <tr>
                        <td><a class="link" href="{{ route('content.quizzes', $lesson) }}">{{ $lesson->title }}</a></td>
                        <td><span class="badge {{ strtolower($lesson->level) }}">{{ strtoupper($lesson->level) }}</span></td>
                        <td><span class="pill">{{ $lesson->cefr }}</span></td>
                        <td>{{ $lesson->is_published ? 'Đã xuất bản' : 'Nháp' }}</td>
                        <td>{{ $lesson->quizzes_count }}</td>
                        <td>✎　🗑</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <b>Chưa có bài học nào</b>
                            <p class="metric">Hãy tạo bài học đầu tiên cho học viên.</p>
                            <button class="btn" onclick="modal('lessonModal')">Thêm mới</button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="lessonModal" class="modal">
    <form class="dialog" onsubmit="toast('Đã lưu bài học');modal('lessonModal');return false">
        <h2>Thêm bài học</h2>
        <label>Tên bài học</label>
        <input required placeholder="Ví dụ: Greetings">
        <label>Cấp độ</label>
        <select>
            <option>beginner</option>
            <option>intermediate</option>
            <option>advanced</option>
        </select>
        <label>CEFR</label>
        <select>
            <option>A1</option>
            <option>A2</option>
            <option>B1</option>
            <option>B2</option>
            <option>C1</option>
            <option>C2</option>
        </select>
        <label><input type="checkbox" style="width:auto"> Xuất bản bài học</label>
        <p>
            <button class="btn">Lưu bài học</button>
            <button type="button" class="btn white" onclick="modal('lessonModal')">Huỷ</button>
        </p>
    </form>
</div>
@endsection
