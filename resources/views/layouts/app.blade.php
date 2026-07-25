<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LexiLingo') — Học Tiếng Anh</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>
<div class="layout-wrapper">

    {{-- ===== SIDEBAR ===== --}}
    <aside class="sidebar">
        {{-- Logo --}}
        <div class="sidebar-brand">
            <i class="bi bi-translate brand-icon"></i>
            <span>LexiLingo</span>
        </div>

        {{-- Navigation --}}
        <nav class="sidebar-nav">
            <a href="{{ url('/') }}"
               class="nav-link {{ request()->is('/') ? 'active' : '' }}">
                <i class="bi bi-house-door"></i>
                <span>HOME</span>
            </a>
            <a href="{{ route('admin.lessons.index') }}"
               class="nav-link {{ request()->routeIs('lessons.*') ? 'active' : '' }}">
                <i class="bi bi-book-half"></i>
                <span>LESSONS</span>
            </a>
            <a href="{{ route('admin.quizzes.index') }}"
               class="nav-link {{ request()->routeIs('quizzes.*') ? 'active' : '' }}">
                <i class="bi bi-patch-question-fill"></i>
                <span>QUIZ</span>
            </a>
            <a href="{{ route('words.index') }}"
               class="nav-link {{ request()->routeIs('words.*') ? 'active' : '' }}">
                <i class="bi bi-card-text"></i>
                <span>WORDS</span>
            </a>
            <a href="{{ route('progress.index') }}"
               class="nav-link {{ request()->routeIs('progress.*') ? 'active' : '' }}">
                <i class="bi bi-graph-up-arrow"></i>
                <span>PROGRESS</span>
            </a>
            <a href="{{ route('bookmarks.index') }}"
               class="nav-link {{ request()->routeIs('bookmarks.*') ? 'active' : '' }}">
                <i class="bi bi-bookmark-heart"></i>
                <span>BOOKMARKS</span>
            </a>
        </nav>

        {{-- Bottom auth --}}
        <div class="sidebar-footer">
            @auth
                <div class="user-info">
                    <i class="bi bi-person-circle me-2"></i>
                    <span>{{ Str::limit(auth()->user()->name, 14) }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-sidebar-logout">
                        <i class="bi bi-box-arrow-left"></i> Đăng xuất
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn-sidebar-login">
                    <i class="bi bi-box-arrow-in-right me-1"></i> ĐĂNG NHẬP
                </a>
            @endauth
        </div>
    </aside>

    {{-- ===== MAIN AREA ===== --}}
    <div class="main-area">

        {{-- Top bar --}}
        <header class="topbar">
            <div class="topbar-title">@yield('page-title', 'Dashboard')</div>
            <div class="topbar-actions">
                @auth
                <div class="streak-badge">
                    <i class="bi bi-fire text-warning"></i>
                    <span>0</span>
                </div>
                <div class="gem-badge">
                    <i class="bi bi-gem text-info"></i>
                    <span>0</span>
                </div>
                <i class="bi bi-bell topbar-icon"></i>
                @endauth
            </div>
        </header>

        {{-- Page Content --}}
        <main class="page-content">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mx-4 mt-3" role="alert">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>

