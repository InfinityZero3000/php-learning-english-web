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
            --teal: #2dd4bf;
            --teal-d: #14b8a6;
            --bg: #0b1512;
            --card-muted: #9fb0aa;
            --line: #16241f;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Be Vietnam Pro', system-ui, sans-serif;
            background: var(--bg);
            color: #fff;
        }

        a { text-decoration: none; }

        .topbar {
            text-align: center;
            padding: 26px 22px 0;
            color: #cfe0da;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: .2px;
        }

        .wrap {
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
        }

        .panel {
            width: 100%;
            max-width: 380px;
            text-align: center;
        }

        .icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 28px;
            border-radius: 50%;
            border: 2px solid var(--teal);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .icon svg { display: block; }

        .icon .dot {
            position: absolute;
            top: 18px;
            right: 20px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--teal);
        }

        h1 {
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 28px;
            margin: 0 0 14px;
        }

        p {
            color: var(--card-muted);
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 34px;
        }

        p b { color: #fff; font-weight: 600; }

        .btn-resend {
            width: 100%;
            border: none;
            cursor: pointer;
            background: var(--teal);
            color: #06201b;
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 16px;
            padding: 15px;
            border-radius: 100px;
        }

        .btn-resend:hover { background: var(--teal-d); }

        .footer-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--teal);
            font-weight: 600;
            font-size: 15px;
        }

        .flash-success {
            margin: 0 0 20px;
            color: var(--teal);
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="topbar">Kiểm tra hộp thư</div>

    <div class="wrap">
        <div class="panel">
            <div class="icon">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="M2 6l10 7 10-7"></path>
                </svg>
                <span class="dot"></span>
            </div>

            <h1>Kiểm tra hộp thư</h1>

            @if(session('success'))
                <div class="flash-success">{{ session('success') }}</div>
            @endif

            <p>Chúng tôi đã gửi liên kết xác minh đến <b>{{ $email }}</b>. Vui lòng kiểm tra email để kích hoạt tài khoản.</p>

            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <button type="submit" class="btn-resend">Gửi lại email xác minh</button>
            </form>

            <a class="footer-link" href="{{ route('login') }}">Quay lại đăng nhập</a>
        </div>
    </div>

</body>
</html>
