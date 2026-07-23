@extends('layouts.auth')

@section('title', 'Kiểm tra hộp thư · LexiLingo')

@section('topbar-switch')
    <a class="topbar-switch" href="{{ route('login') }}">Đăng nhập</a>
@endsection

@section('styles')
    .panel {
        text-align: center;
        padding-top: 6px;
    }

    h1 {
        font-size: 28px;
        margin: 0 0 12px;
    }

    .icon {
        width: 110px;
        height: 110px;
        margin: 0 auto 26px;
        border: 3px solid var(--green);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .icon span.emoji {
        font-size: 46px;
        line-height: 1;
    }

    .icon .dot {
        position: absolute;
        top: 12px;
        right: 16px;
        width: 14px;
        height: 14px;
        background: var(--amber);
        border-radius: 50%;
        border: 3px solid #fff;
    }

    .lead {
        color: var(--muted);
        line-height: 1.65;
        font-size: 15px;
        margin: 0 auto 28px;
        max-width: 340px;
    }

    .lead b { color: var(--ink); }

    .btn-resend {
        width: 100%;
        margin-top: 12px;
        border: 2px solid #e5e5e5;
        cursor: pointer;
        background: #fff;
        color: var(--muted);
        font-family: 'Fredoka', sans-serif;
        font-weight: 700;
        font-size: 15px;
        text-transform: uppercase;
        letter-spacing: .4px;
        padding: 13px;
        border-radius: 16px;
        box-shadow: 0 3px 0 #e5e5e5;
    }

    .btn-resend:active {
        transform: translateY(2px);
        box-shadow: 0 1px 0 #e5e5e5;
    }

    .resend-success {
        color: var(--green-dd);
        font-weight: 600;
        font-size: 14px;
        margin-top: 14px;
    }
@endsection

@section('content')
    <div class="icon">
        <span class="emoji">✉️</span>
        <span class="dot"></span>
    </div>

    <h1>Kiểm tra hộp thư</h1>

    @if(session('notice'))
        <div class="flash-notice">{{ session('notice') }}</div>
    @endif

    <p class="lead">Chúng tôi đã gửi liên kết xác minh đến <b>{{ $email }}</b>. Vui lòng kiểm tra email để kích hoạt tài khoản.</p>

    <a class="btn-submit" href="{{ route('login') }}">Tôi đã xác minh &rarr;</a>

    <form action="{{ route('verification.send') }}" method="POST">
        @csrf
        <button type="submit" class="btn-resend">Gửi lại email xác minh</button>
    </form>

    @if(session('success'))
        <div class="resend-success">&#10003; {{ session('success') }}</div>
    @endif

    <div class="back-link">
        <a href="{{ route('login') }}">Quay lại đăng nhập</a>
    </div>
@endsection
