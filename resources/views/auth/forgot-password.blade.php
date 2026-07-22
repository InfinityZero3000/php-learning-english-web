<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu · LexiLingo</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@700;800;900&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 28px;
            text-align: center;
            margin: 0 0 8px;
        }

        .subtitle {
            color: var(--muted);
            text-align: center;
            margin: 0 0 24px;
        }

        .field-group input {
            width: 100%;
            border: 2px solid #e5e5e5;
            border-radius: 14px;
            background: #f7f7f7;
            padding: 15px 16px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 16px;
            outline: none;
            color: var(--ink);
        }

        .field-group input:focus {
            background: #fff;
            border-color: #b7d9c8;
        }

        .field-error {
            color: #f04438;
            font-size: 13px;
            margin-top: 8px;
        }

        .btn-submit {
            display: block;
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
            text-align: center;
            padding: 15px;
            border-radius: 16px;
            box-shadow: 0 4px 0 var(--green-dd);
        }

        .btn-submit:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 var(--green-dd);
        }

        .back-link {
            text-align: center;
            margin-top: 18px;
        }

        .back-link a {
            color: #1cb0f6;
            font-weight: 700;
            font-size: 14px;
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
        </div>
    </div>

</body>
</html>
