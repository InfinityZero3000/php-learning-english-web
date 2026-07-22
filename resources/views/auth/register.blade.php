<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký · LexiLingo</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green: #12b76a;
            --green-d: #039855;
            --green-dd: #027a48;
            --ink: #0c1f16;
            --muted: #5c6b63;
            --bg: #f2f7f3;
            --card: #ffffff;
            --line: #e2ede7;
            --soft: #eaf5ef;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Be Vietnam Pro', system-ui, sans-serif;
            color: var(--ink);
            background: var(--card);
        }

        a { text-decoration: none; }

        .topbar {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 22px;
            border-bottom: 2px solid #f0f0f0;
        }

        .topbar-close {
            border: none;
            background: transparent;
            cursor: pointer;
            color: #afafaf;
            font-size: 26px;
            font-weight: 700;
            line-height: 1;
            padding: 6px;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-logo {
            width: 30px;
            height: 30px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--green), var(--green-d));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .topbar-logo span {
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            color: #fff;
            font-size: 17px;
        }

        .topbar-brand strong {
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 20px;
            color: var(--green-dd);
        }

        .topbar-switch {
            border: 2px solid #e5e5e5;
            background: #fff;
            cursor: pointer;
            color: var(--green-dd);
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .4px;
            text-transform: uppercase;
            padding: 9px 16px;
            border-radius: 14px;
            box-shadow: 0 3px 0 #e5e5e5;
        }

        .wrap {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 56px 24px;
        }

        .panel {
            width: 100%;
            max-width: 380px;
        }

        h1 {
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 30px;
            text-align: center;
            margin: 0 0 26px;
        }

        .fields {
            border: 2px solid #e5e5e5;
            border-radius: 16px;
            overflow: hidden;
        }

        .fields input {
            width: 100%;
            border: none;
            background: #f7f7f7;
            padding: 15px 16px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 16px;
            outline: none;
            color: var(--ink);
        }

        .fields input:not(:first-child) {
            border-top: 2px solid #e5e5e5;
        }

        .fields input:focus {
            background: #fff;
        }

        .field-error {
            color: #f04438;
            font-size: 13px;
            margin-top: 8px;
        }

        .btn-submit {
            width: 100%;
            margin-top: 18px;
            border: none;
            cursor: pointer;
            background: var(--green);
            color: #fff;
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 15px;
            border-radius: 16px;
            box-shadow: 0 4px 0 var(--green-dd);
        }

        .btn-submit:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 var(--green-dd);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
        }

        .divider .line {
            flex: 1;
            height: 2px;
            background: #f0f0f0;
        }

        .divider span {
            color: #afafaf;
            font-weight: 700;
            font-size: 13px;
        }

        .socials {
            display: flex;
            gap: 12px;
        }

        .btn-social {
            flex: 1;
            border: 2px solid #e5e5e5;
            background: #fff;
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 14px;
            padding: 13px;
            border-radius: 16px;
            box-shadow: 0 3px 0 #e5e5e5;
            cursor: not-allowed;
            opacity: .55;
        }

        .btn-social.facebook { color: #3c5998; }
        .btn-social.google { color: #5b6b62; }

        .footer-link {
            text-align: center;
            color: #afafaf;
            font-size: 13px;
            margin: 24px 0 0;
        }

        .footer-link a {
            color: #1cb0f6;
            font-weight: 700;
        }
    </style>
</head>
<body>

    <header class="topbar">
        <a class="topbar-close" href="{{ url('/') }}">&times;</a>
        <div class="topbar-brand">
            <div class="topbar-logo"><span>L</span></div>
            <strong>LexiLingo</strong>
        </div>
        <a class="topbar-switch" href="{{ route('login') }}">Đăng nhập</a>
    </header>

    <div class="wrap">
        <div class="panel">
            <h1>Tạo hồ sơ</h1>

            <form action="{{ route('register.store') }}" method="POST">
                @csrf

                <div class="fields">
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
        </div>
    </div>

</body>
</html>
