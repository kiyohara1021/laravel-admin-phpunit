@extends('layouts.public')

@section('title', $notice->title)

@section('content')
    <article class="notice-card">
        <p class="notice-meta">{{ $notice->published_at->format('Y-m-d H:i') }}</p>
        <h1 class="public-title">{{ $notice->title }}</h1>
        {{-- 本文はプレーンテキスト。e() でエスケープしてから改行を <br> にする --}}
        <div class="notice-body">{!! nl2br(e($notice->body)) !!}</div>
    </article>

    <p class="public-back">
        <a href="{{ route('notices.index') }}">お知らせ一覧へ戻る</a>
    </p>
@endsection
