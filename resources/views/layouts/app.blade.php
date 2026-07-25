<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','Linguist')</title>
    <script src="https://unpkg.com/lucide@latest"></script>
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
            --blue-soft: #dbeafe;
            --blue: #1d4ed8;
            --amber-soft: #fef3c7;
            --amber: #b45309;
            --red-soft: #fee2e2;
            --red: #b91c1c;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, "Segoe UI", system-ui, sans-serif; color: var(--ink); background: var(--bg); }
        a { text-decoration: none; }
        .shell { display: flex; min-height: 100vh; background: var(--bg); }
        .side { position: fixed; width: 224px; inset: 0 auto 0 0; background: #fff; border-right: 1px solid var(--line); padding: 24px 16px; display: flex; flex-direction: column; }
        .logo { font-size: 27px; font-weight: 900; color: var(--brand); margin-bottom: 24px; padding: 0 8px; }
        .nav { display: flex; flex-direction: column; gap: 6px; }
        .nav a { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 14px; color: #516a76; font-size: 13px; font-weight: 800; border: 1px solid transparent; transition: all .2s ease; }
        .nav a i { width: 18px; height: 18px; }
        .nav a.active, .nav a:hover { background: var(--soft); color: var(--brand); border-color: var(--brand); }
        .main { margin-left: 224px; width: calc(100% - 224px); display: flex; flex-direction: column; }
        .top { padding: 24px 32px 0; }
        .topbar-shell { background: linear-gradient(110deg, var(--brand), var(--brand-dark)); border-radius: 24px; padding: 20px 24px; color: #fff; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 18px 36px rgba(19,74,96,.18); }
        .topbar-shell h2 { margin: 0; font-size: 24px; font-weight: 900; }
        .topbar-shell p { margin: 4px 0 0; font-size: 13px; opacity: .9; }
        .stats { display: flex; align-items: center; gap: 12px; font-weight: 800; }
        .stat-pill { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,.16); padding: 8px 12px; border-radius: 999px; }
        .stat-pill b { color: var(--gold); }
        .content-area { padding: 24px 32px 40px; }
        .hero { background: linear-gradient(110deg, var(--brand), var(--brand-dark)); padding: 24px 28px; color: #fff; border-radius: 24px; margin-bottom: 20px; }
        .hero h1 { font-size: 28px; margin: 0 0 6px; font-weight: 900; }
        .hero p { margin: 0; opacity: .95; }
        .wrap { padding: 0; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .crumb { font-size: 14px; color: #637b87; margin-bottom: 12px; }
        .crumb a { color: var(--brand); font-weight: 800; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(18,50,70,.04); }
        .card + .card { margin-top: 16px; }
        .btn { border: 0; border-radius: 999px; background: var(--brand); color: #fff; font-weight: 800; padding: 11px 18px; cursor: pointer; }
        .btn.white { background: #fff; color: var(--brand); border: 1px solid var(--line); }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .four { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .metric { font-size: 13px; color: #67808c; }
        .number { font-size: 28px; font-weight: 900; color: var(--ink); }
        .badge, .pill { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 11px; font-weight: 900; }
        .beginner { background: var(--blue-soft); color: var(--blue); }
        .intermediate { background: var(--amber-soft); color: var(--amber); }
        .advanced { background: var(--red-soft); color: var(--red); }
        .pill { background: var(--amber-soft); color: var(--amber); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 14px 10px; text-align: left; border-bottom: 1px solid var(--line); }
        .table th { font-size: 11px; color: #718793; text-transform: uppercase; letter-spacing: .08em; }
        .link { color: var(--brand); font-weight: 800; }
        .tabs { display: flex; gap: 10px; margin-bottom: 16px; }
        .tab { padding: 10px 16px; border-radius: 999px; border: 1px solid var(--line); background: #fff; font-weight: 800; color: var(--ink); cursor: pointer; }
        .tab.active { background: var(--brand); color: #fff; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(18, 50, 70, .55); align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .dialog { background: #fff; border-radius: 24px; padding: 24px; width: min(620px, 92vw); box-shadow: 0 20px 50px rgba(18,50,70,.18); }
        input, select, textarea { width: 100%; padding: 11px 12px; border: 1px solid var(--line); border-radius: 12px; margin: 6px 0 14px; color: var(--ink); background: #fff; }
        input:focus, select:focus, textarea:focus { border-color: var(--brand); outline: none; }
        .toast { position: fixed; right: 20px; bottom: 20px; background: var(--ink); color: #fff; padding: 14px 16px; border-radius: 12px; display: none; }
        .toast.show { display: block; }
        @media (max-width: 960px) { .grid, .grid-2, .four { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 720px) {
            .side { width: 72px; padding: 20px 8px; }
            .logo { font-size: 0; }
            .logo:after { content: 'L'; font-size: 27px; }
            .nav span { display: none; }
            .main { margin-left: 72px; width: calc(100% - 72px); }
            .top, .content-area { padding-left: 18px; padding-right: 18px; }
            .topbar-shell { flex-direction: column; align-items: flex-start; gap: 12px; }
            .grid, .grid-2, .four { grid-template-columns: 1fr; }
            .table thead { display: none; }
            .table tr, .table td { display: block; border: 0; padding: 6px 0; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="side">
            <div class="logo">Linguist</div>
            <nav class="nav">
                <a href="/" class="{{ request()->is('/') ? 'active' : '' }}"><i data-lucide="house"></i><span>HOME</span></a>
                <a href="{{ route('vocabulary.index') }}" class="{{ request()->is('words') ? 'active' : '' }}"><i data-lucide="book-open"></i><span>WORDS</span></a>
                <a href="#"><i data-lucide="layers"></i><span>FLASHCARDS</span></a>
                <a class="{{ request()->is('content*') ? 'active' : '' }}" href="{{ route('content.index') }}"><i data-lucide="puzzle"></i><span>QUIZ</span></a>
                <a class="{{ request()->is('learning') ? 'active' : '' }}" href="{{ route('learning.index') }}"><i data-lucide="bar-chart-3"></i><span>PROGRESS</span></a>
                <a class="{{ request()->is('profile') ? 'active' : '' }}" href="{{ route('profile') }}"><i data-lucide="user-circle"></i><span>PROFILE</span></a>
            </nav>
        </aside>

        <section class="main">
            <header class="top">
                <div class="topbar-shell">
                    <div>
                        <h2>@yield('top','Học tiếng Anh')</h2>
                        <p>@yield('subtitle','Học tập cùng LexiLingo')</p>
                    </div>
                    <div class="stats">
                        <span class="stat-pill">🔥 <b>0</b></span>
                        <span class="stat-pill">💎 <b>0</b></span>
                        <span class="stat-pill"><i data-lucide="bell"></i></span>
                    </div>
                </div>
            </header>
            <div class="content-area">@yield('content')</div>
        </section>
    </div>

    <div id="toast" class="toast"></div>
    <script>
        lucide.createIcons();
        function modal(x) {
            document.getElementById(x).classList.toggle('open');
        }
        function toast(x) {
            let e = document.getElementById('toast');
            e.textContent = x;
            e.classList.add('show');
            setTimeout(() => e.classList.remove('show'), 2500);
        }
    </script>
</body>
</html>
