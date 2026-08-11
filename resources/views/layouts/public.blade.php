<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- config('app.name') は「管理画面」なので公開側には出さない --}}
    <title>@yield('title', 'お知らせ')</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<header class="public-header">
    <div class="public-header-inner">
        <p class="public-brand">
            <a href="{{ route('notices.index') }}">お知らせ</a>
        </p>
    </div>
</header>
<main class="public-main">
    @yield('content')
</main>
</body>
</html>
