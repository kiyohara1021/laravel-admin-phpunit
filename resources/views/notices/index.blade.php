@extends('layouts.public')

@section('title', 'お知らせ')

@section('content')
    <h1 class="public-title">お知らせ</h1>

    @forelse ($notices as $notice)
        <article class="notice-card">
            <p class="notice-meta">{{ $notice->published_at->format('Y-m-d') }}</p>
            <h2 class="notice-card-title">
                <a href="{{ route('notices.show', $notice) }}">{{ $notice->title }}</a>
            </h2>
        </article>
    @empty
        <p class="muted">公開中のお知らせはありません。</p>
    @endforelse

    <div class="public-pagination">
        {{ $notices->links() }}
    </div>
@endsection
