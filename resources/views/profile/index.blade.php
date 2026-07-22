@extends('layouts.auth')

@section('title', 'Hồ sơ · LexiLingo')

@section('topbar-switch')
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="topbar-switch">Đăng xuất</button>
    </form>
@endsection

@section('styles')
    .panel {
        max-width: 680px;
    }

    h1 {
        font-size: 28px;
        margin: 0 0 26px;
    }

    .form-label {
        display: block;
        font-family: 'Nunito', sans-serif;
        font-weight: 800;
        font-size: 15px;
        margin-bottom: 8px;
    }

    .form-input {
        width: 100%;
        border: 2px solid var(--line);
        border-radius: 12px;
        background: var(--card);
        padding: 14px 16px;
        font-family: 'Be Vietnam Pro', sans-serif;
        font-size: 16px;
        color: var(--ink);
        outline: none;
        margin-bottom: 20px;
    }

    .form-input:focus {
        border-color: var(--green);
    }

    .form-input[readonly] {
        background: var(--soft);
        color: var(--muted);
        cursor: not-allowed;
    }

    .avatar-section {
        margin-bottom: 24px;
    }

    .avatar-wrap {
        position: relative;
        width: 76px;
        height: 76px;
    }

    .avatar-circle {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: #dfe3ee;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        overflow: hidden;
    }

    .avatar-edit {
        position: absolute;
        right: -4px;
        top: -4px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 3px solid var(--bg);
        background: var(--green);
        color: #fff;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: .6;
        cursor: not-allowed;
    }

    .password-wrap {
        position: relative;
        margin-bottom: 20px;
    }

    .password-wrap.is-last {
        margin-bottom: 26px;
    }

    .password-wrap .form-input {
        padding-right: 48px;
        margin-bottom: 0;
    }

    .toggle-eye {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 18px;
        color: var(--green-dd);
    }

    .form-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .btn-save {
        border: none;
        cursor: pointer;
        background: var(--green);
        color: #fff;
        font-family: 'Nunito', sans-serif;
        font-weight: 800;
        font-size: 14px;
        letter-spacing: .5px;
        text-transform: uppercase;
        padding: 14px 26px;
        border-radius: 14px;
        box-shadow: 0 4px 0 var(--green-dd);
    }

    .btn-save:active {
        transform: translateY(2px);
        box-shadow: 0 2px 0 var(--green-dd);
    }

    .save-success {
        color: var(--green-dd);
        font-weight: 700;
        font-size: 14px;
    }

    .delete-link {
        display: inline-block;
        margin-top: 36px;
        border: none;
        background: none;
        padding: 0;
        color: #d92d20;
        font-family: 'Nunito', sans-serif;
        font-weight: 800;
        font-size: 13px;
        letter-spacing: .5px;
        text-transform: uppercase;
        cursor: pointer;
    }
@endsection

@section('content')
    <h1>Hồ sơ</h1>

    <div class="avatar-section">
        <span class="form-label">Ảnh đại diện</span>
        <div class="avatar-wrap">
            <div class="avatar-circle">
                <svg width="76" height="76" viewBox="0 0 76 76">
                    <circle cx="38" cy="30" r="15" fill="#b0b7c3"></circle>
                    <path d="M12 76c0-15 12-24 26-24s26 9 26 24z" fill="#b0b7c3"></path>
                </svg>
            </div>
            <button type="button" class="avatar-edit" disabled title="Sắp ra mắt">&#9998;</button>
        </div>
    </div>

    <form action="{{ route('profile.update') }}" method="POST">
        @csrf
        @method('PUT')

        <label class="form-label">Tên</label>
        <input class="form-input" type="text" name="name" value="{{ old('name', $user->name) }}">
        @error('name')
            <div class="field-error">{{ $message }}</div>
        @enderror

        <label class="form-label">Email</label>
        <input class="form-input" type="email" value="{{ $user->email }}" readonly>

        <label class="form-label">Mật khẩu hiện tại</label>
        <div class="password-wrap">
            <input class="form-input" type="password" name="current_password" id="current_password">
            <button type="button" class="toggle-eye" data-target="current_password">&#128065;</button>
        </div>
        @error('current_password')
            <div class="field-error">{{ $message }}</div>
        @enderror

        <label class="form-label">Mật khẩu mới</label>
        <div class="password-wrap is-last">
            <input class="form-input" type="password" name="new_password" id="new_password">
            <button type="button" class="toggle-eye" data-target="new_password">&#128065;</button>
        </div>
        @error('new_password')
            <div class="field-error">{{ $message }}</div>
        @enderror

        <div class="form-actions">
            <button type="submit" class="btn-save">Lưu thay đổi</button>
            @if(session('success'))
                <span class="save-success">&#10003; {{ session('success') }}</span>
            @endif
        </div>
    </form>

    <form action="{{ route('profile.destroy') }}" method="POST"
          onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản? Hành động này không thể hoàn tác.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="delete-link">Xóa tài khoản của tôi</button>
    </form>

    <script>
        document.querySelectorAll('.toggle-eye').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target);
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });
    </script>
@endsection
