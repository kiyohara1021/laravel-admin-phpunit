@extends('layouts.admin')

@section('title', 'ダッシュボード')

@section('content')
    <div class="stats">
        <section class="panel stat">
            <span class="muted">お知らせ</span>
            <strong>{{ $noticeCount }}</strong>
        </section>
        <section class="panel stat">
            <span class="muted">公開中</span>
            <strong>{{ $publishedCount }}</strong>
        </section>
        <section class="panel stat">
            <span class="muted">予約公開</span>
            <strong>{{ $scheduledCount }}</strong>
        </section>
        <section class="panel stat">
            <span class="muted">下書き</span>
            <strong>{{ $draftCount }}</strong>
        </section>
        <section class="panel stat">
            <span class="muted">管理者</span>
            <strong>{{ $adminCount }}</strong>
        </section>
    </div>

    <p class="muted">公開中・予約公開・下書きの合計が、お知らせの総数になります。</p>
@endsection
