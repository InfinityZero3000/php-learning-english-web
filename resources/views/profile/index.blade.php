@extends('layouts.app')

@section('title', 'Hồ sơ · LexiLingo')

@section('content')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .profile-card {
            --green: #12b76a;
            --green-d: #039855;
            --green-dd: #027a48;
            --ink: #0c1f16;
            --muted: #5c6b63;
            --bg: #f2f7f3;
            --card: #ffffff;
            --line: #e2ede7;
            --soft: #eaf5ef;

            max-width: 680px;
            margin: 0 auto;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 32px;
            font-family: 'Be Vietnam Pro', system-ui, sans-serif;
            color: var(--ink);
        }

        .profile-card .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 26px;
        }

        .profile-card h1 {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 28px;
            margin: 0;
        }

        .profile-card .btn-logout-link {
            border: 2px solid var(--line);
            background: #fff;
            cursor: pointer;
            color: var(--muted);
            font-family: 'Be Vietnam Pro', sans-serif;
            font-weight: 600;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 10px;
        }

        .profile-card .btn-logout-link:hover {
            border-color: #fecdca;
            color: #d92d20;
        }

        .profile-card .form-label {
            display: block;
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 15px;
            margin-bottom: 8px;
        }

        .profile-card .form-input {
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

        .profile-card .form-input:focus {
            border-color: var(--green);
        }

        .profile-card .form-input[readonly] {
            background: var(--soft);
            color: var(--muted);
            cursor: not-allowed;
        }

        .profile-card .field-error {
            color: #f04438;
            font-size: 13px;
            margin-top: -14px;
            margin-bottom: 16px;
        }

        .profile-card .avatar-section {
            margin-bottom: 24px;
        }

        .profile-card .avatar-wrap {
            position: relative;
            width: 76px;
            height: 76px;
        }

        .profile-card .avatar-circle {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: #dfe3ee;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            overflow: hidden;
        }

        .profile-card .avatar-edit {
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

        .profile-card .password-wrap {
            position: relative;
            margin-bottom: 20px;
        }

        .profile-card .password-wrap.is-last {
            margin-bottom: 26px;
        }

        .profile-card .password-wrap .form-input {
            padding-right: 48px;
            margin-bottom: 0;
        }

        .profile-card .toggle-eye {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--green-dd);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .profile-card .toggle-eye:hover {
            background: var(--soft);
        }

        .profile-card .toggle-eye svg {
            width: 20px;
            height: 20px;
            display: block;
        }

        .profile-card .form-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .profile-card .btn-save {
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

        .profile-card .btn-save:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 var(--green-dd);
        }

        .profile-card .save-success {
            color: var(--green-dd);
            font-weight: 700;
            font-size: 14px;
        }

        .profile-card .delete-link {
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
    </style>

    <div class="profile-card">
        <div class="header-row">
            <h1>Hồ sơ</h1>
        </div>

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
                <button type="button" class="toggle-eye" data-target="current_password" aria-label="Hiện/ẩn mật khẩu">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a19.77 19.77 0 0 1 4.06-5.06M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 7 11 7a19.86 19.86 0 0 1-2.13 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>
            @error('current_password')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <label class="form-label">Mật khẩu mới</label>
            <div class="password-wrap is-last">
                <input class="form-input" type="password" name="new_password" id="new_password">
                <button type="button" class="toggle-eye" data-target="new_password" aria-label="Hiện/ẩn mật khẩu">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a19.77 19.77 0 0 1 4.06-5.06M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 7 11 7a19.86 19.86 0 0 1-2.13 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
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
    </div>

    <script>
        document.querySelectorAll('.toggle-eye').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target);
                var isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                btn.querySelector('.icon-eye').style.display = isHidden ? 'none' : '';
                btn.querySelector('.icon-eye-off').style.display = isHidden ? '' : 'none';
            });
        });
    </script>
@endsection
