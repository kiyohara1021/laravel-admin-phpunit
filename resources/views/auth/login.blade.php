<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ログイン | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>管理画面ログイン</h1>
    <p class="muted">管理者アカウントでログインしてください。</p>

    @if ($errors->any())
        <div class="errors">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">メールアドレス</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">パスワード</label>
        <input id="password" type="password" name="password" required>

        <label class="checkbox">
            <input type="checkbox" name="remember" value="1">
            ログイン状態を保持する
        </label>

        <button class="btn" type="submit">ログイン</button>
    </form>
</div>
</body>
</html>
