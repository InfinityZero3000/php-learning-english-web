@extends('layouts.auth')

@section('title', 'Hồ sơ · LexiLingo')

@section('styles')
    .panel {
        max-width: 760px;
    }

    .profile-banner {
        background: linear-gradient(120deg, var(--green-d), var(--green));
        border-radius: 22px;
        padding: 28px;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 22px;
    }

    .profile-avatar {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .2);
        border: 3px solid rgba(255, 255, 255, .4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Fredoka', sans-serif;
        font-weight: 700;
        font-size: 34px;
        flex-shrink: 0;
    }

    .profile-name {
        font-family: 'Fredoka', sans-serif;
        font-weight: 700;
        font-size: 24px;
    }

    .profile-meta {
        opacity: .9;
        font-size: 14px;
        margin-top: 2px;
    }

    .profile-form {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 20px;
        padding: 26px;
        margin-bottom: 18px;
    }

    .section-title {
        font-family: 'Fredoka', sans-serif;
        font-weight: 600;
        font-size: 19px;
        margin: 0 0 18px;
    }

    .section-title.spaced {
        margin-top: 24px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-field label {
        display: block;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 7px;
    }

    .form-field input {
        width: 100%;
        border: 2px solid var(--line);
        border-radius: 12px;
        padding: 13px 15px;
        font-family: 'Be Vietnam Pro', sans-serif;
        font-size: 15px;
        outline: none;
        color: var(--ink);
    }

    .form-field input:focus {
        border-color: var(--green);
    }

    .form-actions {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-top: 24px;
    }

    .btn-save {
        display: inline-block;
        width: auto;
        margin-top: 0;
        border: none;
        cursor: pointer;
        background: linear-gradient(135deg, var(--green), var(--green-d));
        color: #fff;
        font-family: 'Be Vietnam Pro', sans-serif;
        font-weight: 700;
        font-size: 15px;
        text-transform: none;
        letter-spacing: normal;
        padding: 13px 28px;
        border-radius: 12px;
        box-shadow: none;
    }

    .save-success {
        color: var(--green-dd);
        font-weight: 600;
        font-size: 14px;
    }

    .btn-logout-outline {
        border: 2px solid #fecdca;
        cursor: pointer;
        background: var(--card);
        color: #d92d20;
        font-family: 'Be Vietnam Pro', sans-serif;
        font-weight: 700;
        font-size: 15px;
        padding: 13px 24px;
        border-radius: 12px;
    }
@endsection

@section('content')
    @if(session('success'))
        <div class="flash-success">{{ session('success') }}</div>
    @endif

    <div class="profile-banner">
        <div class="profile-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
        <div>
            <div class="profile-name">{{ $user->name }}</div>
            <div class="profile-meta">{{ $user->email }} · {{ $user->role?->slug === 'admin' ? 'Quản trị viên' : 'Học viên' }}</div>
        </div>
    </div>

    <form class="profile-form" action="{{ route('profile.update') }}" method="POST">
        @csrf
        @method('PUT')

        <h3 class="section-title">Thông tin cá nhân</h3>
        <div class="form-grid">
            <div class="form-field">
                <label>Họ và tên</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}">
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}">
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <h3 class="section-title spaced">Đổi mật khẩu</h3>
        <div class="form-grid">
            <div class="form-field">
                <label>Mật khẩu hiện tại</label>
                <input type="password" name="current_password" placeholder="••••••••">
                @error('current_password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-field">
                <label>Mật khẩu mới</label>
                <input type="password" name="new_password" placeholder="Tối thiểu 8 ký tự">
                @error('new_password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save">Lưu thay đổi</button>
        </div>
    </form>

    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="btn-logout-outline">&#9099; Đăng xuất</button>
    </form>
@endsection
