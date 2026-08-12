@extends('layouts.public')

@section('title', 'お知らせ')

@section('content')
    <h1 class="public-title">お知らせ</h1>

    <form class="public-search" method="GET" action="{{ route('notices.index') }}">
        <label for="q">キーワード</label>
        <input id="q" type="search" name="q" value="{{ $filter->keyword }}" placeholder="タイトル・本文">
        <button class="btn" type="submit">検索</button>
    </form>

    @if ($filter->hasKeyword())
        <p class="muted filter-summary">該当 {{ $notices->total() }} 件</p>
    @endif

    @forelse ($notices as $notice)
        <article class="notice-card">
            <p class="notice-meta">{{ $notice->published_at->format('Y-m-d') }}</p>
            <h2 class="notice-card-title">
                <a href="{{ route('notices.show', $notice) }}">{{ $notice->title }}</a>
            </h2>
        </article>
    @empty
        <p class="muted">{{ $filter->hasKeyword() ? '検索条件に一致するお知らせはありません。' : '公開中のお知らせはありません。' }}</p>
    @endforelse

    <div class="public-pagination">
        {{ $notices->links() }}
    </div>
@endsection
