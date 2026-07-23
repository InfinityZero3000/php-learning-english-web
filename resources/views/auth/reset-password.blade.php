@extends('layouts.auth')

@section('title', 'Đặt lại mật khẩu · LexiLingo')

@section('topbar-switch')
    <a class="topbar-switch" href="{{ route('login') }}">Đăng nhập</a>
@endsection

@section('styles')
    h1 {
        font-size: 28px;
        margin: 0 0 8px;
    }
@endsection

@section('content')
    <h1>Đặt lại mật khẩu</h1>
    <p class="subtitle">Nhập mật khẩu mới cho tài khoản của bạn.</p>

    <form action="{{ route('password.update') }}" method="POST">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">

        <div class="field-group">
            <input type="password" name="password" placeholder="Mật khẩu mới (tối thiểu 8 ký tự)">
            <input type="password" name="password_confirmation" placeholder="Xác nhận mật khẩu mới">
        </div>

        @error('email')
            <div class="field-error">{{ $message }}</div>
        @enderror
        @error('password')
            <div class="field-error">{{ $message }}</div>
        @enderror

        <button type="submit" class="btn-submit">Đặt lại mật khẩu</button>

        <div class="back-link">
            <a href="{{ route('login') }}">&larr; Quay lại đăng nhập</a>
        </div>
    </form>
@endsection
