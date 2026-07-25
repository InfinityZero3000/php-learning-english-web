<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Linguist - Học tiếng Anh')</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        :root {
            --ink: #1a1a2e;
            --brand: #4f46e5;
            --brand-light: #6366f1;
            --brand-dark: #3730a3;
            --brand-soft: #eef2ff;
            --bg: #f8fafc;
            --surface: #ffffff;
            --line: #e2e8f0;
            --gold: #f59e0b;
            --gold-soft: #fef3c7;
            --green: #10b981;
            --green-soft: #d1fae5;
            --red: #ef4444;
            --red-soft: #fee2e2;
            --blue: #3b82f6;
            --blue-soft: #dbeafe;
            --amber: #f59e0b;
            --amber-soft: #fef3c7;
            --purple: #8b5cf6;
            --purple-soft: #ede9fe;
            --pink: #ec4899;
            --pink-soft: #fce7f3;
            --gray: #64748b;
            --gray-soft: #f1f5f9;
            --radius: 16px;
            --radius-sm: 10px;
            --shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md: 0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; }

        /* Layout */
        .shell { display: flex; min-height: 100vh; }

        /* Sidebar */
        .side {
            position: fixed; top: 0; left: 0; bottom: 0; width: 240px;
            background: linear-gradient(180deg, #1e1b4b 0%, #312e81 100%);
            color: #e0e7ff; padding: 24px 16px; display: flex; flex-direction: column;
            z-index: 100; overflow-y: auto;
        }
        .logo {
            display: flex; align-items: center; gap: 10px; padding: 0 8px 24px;
            border-bottom: 1px solid rgba(255,255,255,.1); margin-bottom: 20px;
        }
        .logo-icon {
            width: 40px; height: 40px; background: linear-gradient(135deg, #818cf8, #6366f1);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .logo-text { font-size: 20px; font-weight: 800; letter-spacing: -.5px; color: #fff; }
        .logo-text span { color: #a5b4fc; font-weight: 500; font-size: 12px; display: block; }

        .nav { display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .nav-section { font-size: 10px; text-transform: uppercase; letter-spacing: .1em; color: #6366f1; padding: 16px 12px 6px; font-weight: 700; }
        .nav a, .nav button.nav-btn {
            display: flex; align-items: center; gap: 12px; padding: 10px 12px;
            border-radius: var(--radius-sm); color: #c7d2fe; font-size: 13.5px; font-weight: 600;
            transition: all .15s ease; border: none; background: none; cursor: pointer; width: 100%; text-align: left;
        }
        .nav a:hover, .nav button.nav-btn:hover { background: rgba(255,255,255,.08); color: #fff; }
        .nav a.active, .nav button.nav-btn.active { background: rgba(99,102,241,.3); color: #fff; font-weight: 700; }
        .nav a i, .nav button.nav-btn i { width: 18px; height: 18px; flex-shrink: 0; }
        .nav-badge {
            margin-left: auto; background: var(--brand-light); color: #fff; font-size: 10px;
            padding: 2px 7px; border-radius: 999px; font-weight: 700;
        }
        .side-footer {
            border-top: 1px solid rgba(255,255,255,.1); padding-top: 12px; margin-top: auto;
        }
        .side-footer .user-info { display: flex; align-items: center; gap: 10px; padding: 8px 12px; }
        .side-footer .avatar {
            width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #818cf8, #a78bfa);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #fff;
        }

        /* Main */
        .main { margin-left: 240px; width: calc(100% - 240px); display: flex; flex-direction: column; }

        /* Topbar */
        .topbar {
            background: var(--surface); border-bottom: 1px solid var(--line);
            padding: 16px 32px; display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .topbar-left h1 { font-size: 20px; font-weight: 800; }
        .topbar-left .breadcrumb { font-size: 12px; color: var(--gray); }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
            border-radius: 999px; font-weight: 700; font-size: 13px; cursor: pointer;
            transition: all .15s ease; border: none; white-space: nowrap;
        }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-outline { background: #fff; color: var(--brand); border: 2px solid var(--brand); }
        .btn-outline:hover { background: var(--brand-soft); }
        .btn-ghost { background: transparent; color: var(--gray); }
        .btn-ghost:hover { background: var(--gray-soft); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-lg { padding: 14px 24px; font-size: 15px; }
        .btn-icon { padding: 10px; border-radius: 50%; }
        .btn-danger { background: var(--red); color: #fff; }
        .btn-success { background: var(--green); color: #fff; }

        /* Content */
        .content-area { padding: 28px 32px 40px; }
        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-size: 26px; font-weight: 900; color: var(--ink); }
        .page-header p { color: var(--gray); margin-top: 4px; font-size: 14px; }

        /* Hero / Banner */
        .hero {
            background: linear-gradient(135deg, #312e81 0%, #4f46e5 40%, #6366f1 100%);
            border-radius: var(--radius); padding: 40px 36px; color: #fff;
            position: relative; overflow: hidden; margin-bottom: 28px;
        }
        .hero::after {
            content: ''; position: absolute; right: -40px; top: -40px;
            width: 220px; height: 220px; background: rgba(255,255,255,.06);
            border-radius: 50%;
        }
        .hero h1 { font-size: 32px; font-weight: 900; margin-bottom: 8px; position: relative; z-index: 1; }
        .hero p { font-size: 15px; opacity: .9; max-width: 520px; position: relative; z-index: 1; }
        .hero-stats { display: flex; gap: 24px; margin-top: 20px; position: relative; z-index: 1; }
        .hero-stat { text-align: center; }
        .hero-stat .val { font-size: 36px; font-weight: 900; }
        .hero-stat .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity: .8; }

        /* Cards */
        .card {
            background: var(--surface); border: 1px solid var(--line);
            border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);
            transition: box-shadow .2s ease;
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card + .card { margin-top: 16px; }
        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--line);
        }
        .card-header h3 { font-size: 16px; font-weight: 800; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius);
            padding: 20px 24px; display: flex; align-items: center; gap: 16px;
            box-shadow: var(--shadow);
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px; display: flex;
            align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
        }
        .stat-icon.blue { background: var(--blue-soft); color: var(--blue); }
        .stat-icon.green { background: var(--green-soft); color: var(--green); }
        .stat-icon.amber { background: var(--amber-soft); color: var(--amber); }
        .stat-icon.purple { background: var(--purple-soft); color: var(--purple); }
        .stat-icon.pink { background: var(--pink-soft); color: var(--pink); }
        .stat-info .stat-val { font-size: 26px; font-weight: 900; }
        .stat-info .stat-lbl { font-size: 12px; color: var(--gray); font-weight: 600; }

        /* Grid layouts */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }

        /* Toolbar */
        .toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
        .toolbar-spacer { flex: 1; }

        /* Search */
        .search-box {
            display: flex; align-items: center; gap: 8px; background: var(--surface);
            border: 2px solid var(--line); border-radius: 999px; padding: 8px 16px;
            transition: border-color .2s; max-width: 380px; flex: 1;
        }
        .search-box:focus-within { border-color: var(--brand); }
        .search-box input {
            border: none; outline: none; background: transparent; flex: 1;
            font-size: 14px; color: var(--ink); padding: 4px 0;
        }
        .search-box i { width: 18px; height: 18px; color: var(--gray); }

        /* Table */
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th {
            padding: 12px 16px; text-align: left; font-size: 11px; color: var(--gray);
            text-transform: uppercase; letter-spacing: .06em; font-weight: 700;
            background: var(--gray-soft); border-bottom: 2px solid var(--line);
        }
        .table td { padding: 14px 16px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .table tr:hover { background: #f8fafc; }
        .table .word-cell { font-weight: 700; font-size: 15px; color: var(--brand); }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
            border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .02em;
        }
        .badge-beginner { background: var(--green-soft); color: #065f46; }
        .badge-intermediate { background: var(--amber-soft); color: #92400e; }
        .badge-advanced { background: var(--red-soft); color: #991b1b; }
        .badge-enriched { background: var(--purple-soft); color: #6d28d9; }
        .badge-noun { background: #dbeafe; color: #1e40af; }
        .badge-verb { background: #fce7f3; color: #9d174d; }
        .badge-adjective { background: #ede9fe; color: #5b21b6; }
        .badge-default { background: var(--gray-soft); color: var(--gray); }

        /* Flashcard */
        .flashcard-container {
            perspective: 1000px; width: 100%; max-width: 420px; margin: 0 auto;
        }
        .flashcard {
            width: 100%; height: 280px; position: relative;
            transform-style: preserve-3d; transition: transform .6s ease;
            cursor: pointer;
        }
        .flashcard.flipped { transform: rotateY(180deg); }
        .flashcard-face {
            position: absolute; inset: 0; backface-visibility: hidden;
            border-radius: var(--radius); display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 32px;
            box-shadow: var(--shadow-lg);
        }
        .flashcard-front {
            background: linear-gradient(135deg, #312e81, #4f46e5);
            color: #fff;
        }
        .flashcard-front .word { font-size: 36px; font-weight: 900; margin-bottom: 8px; }
        .flashcard-front .phonetic { font-size: 16px; opacity: .8; }
        .flashcard-back {
            background: var(--surface); border: 2px solid var(--line);
            transform: rotateY(180deg); color: var(--ink);
        }
        .flashcard-back .meaning { font-size: 28px; font-weight: 800; color: var(--brand); margin-bottom: 8px; }
        .flashcard-back .example { font-size: 15px; color: var(--gray); font-style: italic; margin-top: 12px; }

        /* Progress bars */
        .progress-bar {
            height: 10px; background: var(--gray-soft); border-radius: 999px; overflow: hidden;
        }
        .progress-fill { height: 100%; border-radius: 999px; transition: width .6s ease; }
        .progress-fill.blue { background: linear-gradient(90deg, #6366f1, #3b82f6); }
        .progress-fill.green { background: linear-gradient(90deg, #10b981, #34d399); }
        .progress-fill.amber { background: linear-gradient(90deg, #f59e0b, #fbbf24); }

        /* Quiz */
        .quiz-option {
            display: block; width: 100%; padding: 14px 18px; border: 2px solid var(--line);
            border-radius: var(--radius-sm); background: #fff; cursor: pointer;
            font-size: 15px; text-align: left; transition: all .15s; margin-bottom: 8px;
        }
        .quiz-option:hover { border-color: var(--brand); background: var(--brand-soft); }
        .quiz-option.selected { border-color: var(--brand); background: var(--brand-soft); font-weight: 700; }
        .quiz-option.correct { border-color: var(--green); background: var(--green-soft); color: #065f46; }
        .quiz-option.wrong { border-color: var(--red); background: var(--red-soft); color: #991b1b; }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
            z-index: 200; align-items: center; justify-content: center; backdrop-filter: blur(4px);
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: var(--surface); border-radius: var(--radius); padding: 28px;
            width: min(600px, 92vw); max-height: 90vh; overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        .modal h3 { font-size: 20px; font-weight: 800; margin-bottom: 16px; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .close-btn {
            position: absolute; top: 12px; right: 12px; background: none; border: none;
            cursor: pointer; padding: 6px; border-radius: 50%;
        }

        /* Inputs */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--ink); margin-bottom: 6px; }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: 10px 14px; border: 2px solid var(--line); border-radius: var(--radius-sm);
            font-size: 14px; color: var(--ink); background: #fff; transition: border-color .15s;
            font-family: inherit;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--brand); outline: none; }
        .form-textarea { min-height: 80px; resize: vertical; }
        .form-row { display: flex; gap: 12px; }
        .form-row > * { flex: 1; }

        /* Pagination */
        .pagination { display: flex; gap: 6px; justify-content: center; margin-top: 24px; }
        .pagination a, .pagination span {
            padding: 8px 14px; border-radius: var(--radius-sm); font-weight: 600; font-size: 13px;
            border: 1px solid var(--line); background: #fff; color: var(--ink);
        }
        .pagination .active { background: var(--brand); color: #fff; border-color: var(--brand); }
        .pagination a:hover { background: var(--brand-soft); }

        /* Toast */
        .toast {
            position: fixed; bottom: 24px; right: 24px; padding: 14px 20px;
            border-radius: var(--radius-sm); color: #fff; font-weight: 700; font-size: 14px;
            z-index: 300; display: none; box-shadow: var(--shadow-lg);
        }
        .toast.show { display: block; animation: slideUp .3s ease; }
        .toast-success { background: var(--green); }
        .toast-error { background: var(--red); }
        .toast-info { background: var(--blue); }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { width: 56px; height: 56px; color: #cbd5e1; margin-bottom: 12px; }
        .empty-state h3 { font-size: 18px; color: var(--gray); margin-bottom: 6px; }
        .empty-state p { color: #94a3b8; font-size: 14px; }

        /* Tags / Categories */
        .tag-list { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .tag {
            padding: 6px 14px; border-radius: 999px; font-size: 12px; font-weight: 700;
            border: 1px solid var(--line); cursor: pointer; transition: all .15s; background: #fff;
        }
        .tag:hover, .tag.active { background: var(--brand-soft); border-color: var(--brand); color: var(--brand); }

        /* Chart container */
        .chart-wrap { position: relative; width: 100%; }
        .chart-wrap canvas { max-height: 300px; }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed var(--line); border-radius: var(--radius); padding: 40px;
            text-align: center; cursor: pointer; transition: all .2s;
        }
        .upload-zone:hover { border-color: var(--brand); background: var(--brand-soft); }
        .upload-zone i { width: 40px; height: 40px; color: var(--gray); margin-bottom: 8px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-3, .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .side { width: 64px; padding: 16px 8px; }
            .side .logo-text, .side .nav span, .side .nav-section, .side .nav-badge,
            .side .side-footer .user-info span { display: none; }
            .side .nav a, .side .nav button.nav-btn { justify-content: center; padding: 12px; }
            .main { margin-left: 64px; width: calc(100% - 64px); }
            .content-area { padding: 16px; }
            .topbar { padding: 12px 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .grid, .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .hero { padding: 24px 20px; }
            .hero h1 { font-size: 24px; }
            .hero-stats { flex-wrap: wrap; gap: 16px; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .table thead { display: none; }
            .table tr { display: block; margin-bottom: 12px; border: 1px solid var(--line); border-radius: var(--radius-sm); }
            .table td { display: block; border: none; padding: 8px 16px; }
            .table td:before { content: attr(data-label); font-weight: 700; font-size: 10px; text-transform: uppercase; color: var(--gray); display: block; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <!-- Sidebar -->
        <aside class="side">
            <div class="logo">
                <div class="logo-icon">📚</div>
                <div class="logo-text">Linguist<span>Học tiếng Anh</span></div>
            </div>
            <nav class="nav">
                <div class="nav-section">Menu chính</div>
                <a href="/" class="{{ request()->is('/') ? 'active' : '' }}">
                    <i data-lucide="home"></i><span>Trang chủ</span>
                </a>
                <a href="{{ route('vocabularies.index') }}" class="{{ request()->is('vocabularies*') && !request()->is('vocabularies/flashcards*') ? 'active' : '' }}">
                    <i data-lucide="book-open"></i><span>Từ vựng</span>
                </a>
                <a href="{{ route('vocabularies.flashcards') }}" class="{{ request()->is('vocabularies/flashcards*') ? 'active' : '' }}">
                    <i data-lucide="layers"></i><span>Flashcards</span>
                </a>
                <a href="{{ route('quizzes.index') }}" class="{{ request()->is('quizzes*') ? 'active' : '' }}">
                    <i data-lucide="brain"></i><span>Quiz</span>
                </a>
                @auth
                <a href="{{ route('dashboard') }}" class="{{ request()->is('dashboard*') ? 'active' : '' }}">
                    <i data-lucide="trending-up"></i><span>Tiến độ</span>
                </a>
                <a href="{{ route('vocabularies.import') }}" class="{{ request()->is('vocabularies/import*') ? 'active' : '' }}">
                    <i data-lucide="upload-cloud"></i><span>Import</span>
                </a>
                <div class="nav-section">Cá nhân</div>
                <a href="{{ route('bookmarks.index') }}" class="{{ request()->is('bookmarks*') ? 'active' : '' }}">
                    <i data-lucide="bookmark"></i><span>Đã lưu</span>
                </a>
                <a href="{{ route('profile') }}" class="{{ request()->is('profile*') ? 'active' : '' }}">
                    <i data-lucide="user-circle"></i><span>Hồ sơ</span>
                </a>
                @endauth
            </nav>
            <div class="side-footer">
                @auth
                <div class="user-info">
                    <div class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <span style="font-size:13px;font-weight:700;">{{ auth()->user()->name ?? 'User' }}</span>
                </div>
                <form action="{{ route('logout') }}" method="POST" style="margin-top:8px;">
                    @csrf
                    <button type="submit" class="nav-btn" style="color:#f87171;">
                        <i data-lucide="log-out" style="width:16px;height:16px;"></i><span>Đăng xuất</span>
                    </button>
                </form>
                @else
                <a href="{{ route('login') }}" class="btn btn-outline" style="width:100%;justify-content:center;color:#fff;border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.1);">
                    <i data-lucide="log-in" style="width:16px;height:16px;"></i> Đăng nhập
                </a>
                @endauth
            </div>
        </aside>

        <!-- Main Content -->
        <section class="main">
            @hasSection('topbar')
                @yield('topbar')
            @else
                <div class="topbar">
                    <div class="topbar-left">
                        <h1>@yield('top', 'Học tiếng Anh')</h1>
                        @hasSection('breadcrumb')
                            <span class="breadcrumb">/ @yield('breadcrumb')</span>
                        @endif
                    </div>
                    <div class="topbar-actions">
                        @auth
                            <span style="font-size:13px;color:var(--gray);font-weight:600;">
                                🔥 {{ auth()->user()->streak_days ?? 0 }} ngày
                            </span>
                            <span style="font-size:13px;color:var(--gray);font-weight:600;">
                                💎 {{ auth()->user()->xp_points ?? 0 }} XP
                            </span>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Đăng nhập</a>
                            <a href="{{ route('register') }}" class="btn btn-outline btn-sm">Đăng ký</a>
                        @endauth
                    </div>
                </div>
            @endif

            <div class="content-area">
                @if(session('success'))
                    <div style="background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;border:1px solid #a7f3d0;">
                        ✅ {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;border:1px solid #fecaca;">
                        ❌ {{ session('error') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </section>
    </div>

    <div id="toast" class="toast"></div>
    <script>
        lucide.createIcons();
        function openModal(id) { document.getElementById(id).classList.add('open'); }
        function closeModal(id) { document.getElementById(id).classList.remove('open'); }
        function showToast(msg, type = 'success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast toast-' + type + ' show';
            setTimeout(() => t.classList.remove('show'), 3000);
        }
        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
        });
        // Close modals on Escape
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open')); });
    </script>
    @stack('scripts')
</body>
</html>