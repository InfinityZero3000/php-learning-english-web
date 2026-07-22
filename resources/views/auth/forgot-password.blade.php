@extends('layouts.auth')

@section('title', 'Quên mật khẩu · LexiLingo')

@section('topbar-switch')
    <a class="topbar-switch" href="{{ route('login') }}">Đăng nhập</a>
@endsection

@section('styles')
    h1 {
        font-size: 28px;
        margin: 0 0 8px;
    }

    /* --- sent confirmation state --- */
    .sent {
        text-align: center;
    }

    .sent .emoji {
        font-size: 56px;
    }

    .sent h1 {
        margin: 10px 0 8px;
    }

    .sent p {
        color: var(--muted);
        line-height: 1.6;
        margin: 0 0 24px;
    }

    .sent p b {
        color: var(--ink);
    }
@endsection

@section('content')
    @if(session('sentEmail'))
        <div class="sent">
            <div class="emoji">📬</div>
            <h1>Đã gửi email!</h1>
            <p>Link đặt lại mật khẩu đã gửi tới <b>{{ session('sentEmail') }}</b>. Kiểm tra hộp thư của bạn nhé.</p>
            <a class="btn-submit" href="{{ route('login') }}">Về đăng nhập</a>
        </div>
    @else
        <h1>Quên mật khẩu?</h1>
        <p class="subtitle">Nhập email, chúng tôi sẽ gửi link đặt lại.</p>

        <form action="{{ route('password.email') }}" method="POST">
            @csrf

            <div class="field-group">
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email">
            </div>

            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn-submit">Gửi link đặt lại</button>

            <div class="back-link">
                <a href="{{ route('login') }}">&larr; Quay lại đăng nhập</a>
            </div>
        </form>
    @endif
@endsection
