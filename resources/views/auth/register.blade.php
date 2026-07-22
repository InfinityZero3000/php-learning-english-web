@extends('layouts.auth')

@section('title', 'Đăng ký · LexiLingo')

@section('topbar-switch')
    <a class="topbar-switch" href="{{ route('login') }}">Đăng nhập</a>
@endsection

@section('content')
    <h1>Tạo hồ sơ</h1>

    <form action="{{ route('register.store') }}" method="POST">
        @csrf

        <div class="field-group">
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Họ và tên">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email">
            <input type="password" name="password" placeholder="Mật khẩu (tối thiểu 8 ký tự)">
            <input type="password" name="password_confirmation" placeholder="Xác nhận mật khẩu">
        </div>

        @error('name')
            <div class="field-error">{{ $message }}</div>
        @enderror
        @error('email')
            <div class="field-error">{{ $message }}</div>
        @enderror
        @error('password')
            <div class="field-error">{{ $message }}</div>
        @enderror

        <button type="submit" class="btn-submit">Tạo tài khoản</button>

        <div class="divider">
            <div class="line"></div>
            <span>HOẶC</span>
            <div class="line"></div>
        </div>

        <div class="socials">
            <button type="button" class="btn-social facebook" disabled title="Sắp ra mắt">f Facebook</button>
            <button type="button" class="btn-social google" disabled title="Sắp ra mắt">G Google</button>
        </div>

        <p class="footer-link">
            Đã có tài khoản?
            <a href="{{ route('login') }}">Đăng nhập</a>
        </p>
    </form>
@endsection
