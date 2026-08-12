@extends('layouts.admin')

@section('title', 'お知らせ')

@section('content')
    <div class="actions">
        <p class="muted">公開・予約公開・下書きのお知らせを管理します。</p>
        <div class="actions-right">
            <a class="btn secondary" href="{{ route('admin.notices.trashed') }}">ゴミ箱（{{ $trashedCount }}）</a>
            <a class="btn" href="{{ route('admin.notices.create') }}">新規作成</a>
        </div>
    </div>

    <form class="filters" method="GET" action="{{ route('admin.notices.index') }}">
        <div class="filter-field">
            <label for="q">キーワード</label>
            <input id="q" type="search" name="q" value="{{ $filter->keyword }}" placeholder="タイトル・本文">
        </div>
        <div class="filter-field">
            <label for="status">状態</label>
            <select id="status" name="status">
                <option value="">すべて</option>
                @foreach (\App\Enums\NoticeStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filter->status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field filter-buttons">
            <button class="btn" type="submit">絞り込む</button>
            @if ($filter->isActive())
                <a class="btn secondary" href="{{ route('admin.notices.index') }}">条件をクリア</a>
            @endif
        </div>
    </form>

    @if ($filter->isActive())
        <p class="muted filter-summary">該当 {{ $notices->total() }} 件</p>
    @endif

    <div class="panel" style="padding: 8px 16px 16px;">
        <table>
            <thead>
            <tr>
                <th>タイトル</th>
                <th>状態</th>
                <th>作成者</th>
                <th>公開日時</th>
                <th>更新日</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($notices as $notice)
                <tr>
                    <td>{{ $notice->title }}</td>
                    <td>
                        <span class="badge {{ $notice->status()->value }}">{{ $notice->status()->label() }}</span>
                    </td>
                    <td>{{ $notice->user?->name ?? '—' }}</td>
                    <td>{{ $notice->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $notice->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="row-actions">
                        <a class="btn secondary" href="{{ route('admin.notices.edit', $notice) }}">編集</a>
                        <form class="inline-form" method="POST" action="{{ route('admin.notices.destroy', $notice) }}" onsubmit="return confirm('このお知らせを削除しますか？')">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">{{ $filter->isActive() ? '検索条件に一致するお知らせはありません。' : 'お知らせはまだありません。' }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 16px;">
            {{ $notices->links() }}
        </div>
    </div>
@endsection
