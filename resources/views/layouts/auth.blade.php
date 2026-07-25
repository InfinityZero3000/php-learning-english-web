<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'LexiLingo')</title>

    <style>
        :root {
            --ink: #123246;
            --brand: #1c5d78;
            --brand-dark: #134a60;
            --bg: #f5f7f9;
            --line: #e2e8ee;
            --gold: #e0a63c;
            --surface: #ffffff;
            --soft: #e5f1f6;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, "Segoe UI", system-ui, sans-serif;
            color: var(--ink);
            background: linear-gradient(135deg, #f8fbfd 0%, var(--bg) 100%);
        }
        a { text-decoration: none; }

        .topbar {
            height: 66px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
        }
        .topbar-close {
            border: none;
            background: transparent;
            cursor: pointer;
            color: #8a9aa8;
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            padding: 6px;
        }
        .topbar-switch {
            border: 1px solid var(--line);
            background: #fff;
            cursor: pointer;
            color: var(--brand);
            font-weight: 800;
            font-size: 14px;
            padding: 9px 16px;
            border-radius: 999px;
        }
        .wrap {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 24px 56px;
        }
        .panel {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 16px 40px rgba(18, 50, 70, .08);
        }
        h1 {
            font-weight: 900;
            font-size: 28px;
            text-align: center;
            margin: 0 0 8px;
            color: var(--ink);
        }
        .subtitle {
            color: #5f7485;
            text-align: center;
            margin: 0 0 24px;
        }
        .flash-success {
            background: var(--soft);
            color: var(--brand-dark);
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            padding: 12px 16px;
            border-radius: 12px;
            margin: 0 0 20px;
        }
        .flash-notice {
            background: #fff7e6;
            color: #b45309;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            padding: 12px 16px;
            border-radius: 12px;
            margin: 0 0 20px;
        }
        .field-group { display: flex; flex-direction: column; gap: 12px; }
        .field-group input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fcfdfe;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            color: var(--ink);
        }
        .field-group input:focus {
            background: #fff;
            border-color: var(--brand);
        }
        .field-error {
            color: #b91c1c;
            font-size: 13px;
            margin-top: 8px;
        }
        .btn-submit {
            display: block;
            width: 100%;
            margin-top: 18px;
            border: none;
            cursor: pointer;
            background: linear-gradient(110deg, var(--brand), var(--brand-dark));
            color: #fff;
            font-weight: 800;
            font-size: 16px;
            padding: 14px;
            border-radius: 999px;
        }
        .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
        .divider .line { flex: 1; height: 1px; background: var(--line); }
        .divider span { color: #8a9aa8; font-weight: 700; font-size: 13px; }
        .terms { text-align: center; color: #8a9aa8; font-size: 12px; line-height: 1.6; margin: 24px 0 0; }
        .footer-link { text-align: center; color: #8a9aa8; font-size: 13px; margin: 24px 0 0; }
        .footer-link a, .back-link a { color: var(--brand); font-weight: 700; }
        .back-link { text-align: center; margin-top: 18px; }
        @yield('styles')
    </style>
</head>
<body>
    <header class="topbar">
        <a class="topbar-close" href="{{ url('/') }}">&times;</a>
        @yield('topbar-switch')
    </header>

    <div class="wrap">
        <div class="panel">
            @yield('content')
        </div>
    </div>
</body>
</html>