<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '管理画面') | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <p class="brand">管理画面</p>
        <nav class="nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">ダッシュボード</a>
            <a href="{{ route('admin.notices.index') }}" class="{{ request()->routeIs('admin.notices.*') ? 'active' : '' }}">お知らせ</a>
        </nav>
    </aside>
    <main class="main">
        <div class="topbar">
            <div>
                <h1>@yield('title', '管理画面')</h1>
                <p class="muted">PHPUnit のテストを添えた管理画面サンプルです。</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn secondary" type="submit">{{ auth()->user()->name }} としてログアウト</button>
            </form>
        </div>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
