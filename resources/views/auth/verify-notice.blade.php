<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm tra hộp thư · LexiLingo</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green: #12b76a;
            --green-d: #039855;
            --green-dd: #027a48;
            --amber: #f79009;
            --amber-d: #dc6803;
            --ink: #0c1f16;
            --muted: #5c6b63;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Be Vietnam Pro', system-ui, sans-serif;
            color: var(--ink);
            background: #fff;
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
            text-align: center;
            padding-top: 6px;
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

        h1 {
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 28px;
            margin: 0 0 12px;
        }

        p {
            color: var(--muted);
            line-height: 1.65;
            font-size: 15px;
            margin: 0 auto 28px;
            max-width: 340px;
        }

        p b { color: var(--ink); }

        .btn-verify {
            width: 100%;
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
            display: block;
            text-align: center;
        }

        .btn-verify:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 var(--green-dd);
        }

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

        .flash-notice {
            background: #fff5e6;
            color: var(--amber-d);
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            padding: 12px 16px;
            border-radius: 12px;
            margin: 0 0 20px;
        }

        .footer-link {
            display: block;
            margin-top: 20px;
            color: #1cb0f6;
            font-weight: 700;
            font-size: 14px;
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
            <div class="icon">
                <span class="emoji">✉️</span>
                <span class="dot"></span>
            </div>

            <h1>Kiểm tra hộp thư</h1>

            @if(session('notice'))
                <div class="flash-notice">{{ session('notice') }}</div>
            @endif

            <p>Chúng tôi đã gửi liên kết xác minh đến <b>{{ $email }}</b>. Vui lòng kiểm tra email để kích hoạt tài khoản.</p>

            <a class="btn-verify" href="{{ route('login') }}">Tôi đã xác minh &rarr;</a>

            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <button type="submit" class="btn-resend">Gửi lại email xác minh</button>
            </form>

            @if(session('success'))
                <div class="resend-success">&#10003; {{ session('success') }}</div>
            @endif

            <a class="footer-link" href="{{ route('login') }}">Quay lại đăng nhập</a>
        </div>
    </div>

</body>
</html>
