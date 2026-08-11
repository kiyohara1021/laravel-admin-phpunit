@extends('layouts.admin')

@section('title', 'お知らせ')

@section('content')
    <div class="actions">
        <p class="muted">公開・予約公開・下書きのお知らせを管理します。</p>
        <a class="btn" href="{{ route('admin.notices.create') }}">新規作成</a>
    </div>

    <div class="panel" style="padding: 8px 16px 16px;">
        <table>
            <thead>
            <tr>
                <th>タイトル</th>
                <th>状態</th>
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
                    <td colspan="5" class="muted">お知らせはまだありません。</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 16px;">
            {{ $notices->links() }}
        </div>
    </div>
@endsection
