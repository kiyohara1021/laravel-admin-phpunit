@extends('layouts.admin')

@section('title', 'ゴミ箱')

@section('content')
    <div class="actions">
        <p class="muted">削除したお知らせです。復元するか、完全に削除できます。</p>
        <div class="actions-right">
            <a class="btn secondary" href="{{ route('admin.notices.index') }}">お知らせ一覧へ戻る</a>
        </div>
    </div>

    <div class="panel" style="padding: 8px 16px 16px;">
        <table>
            <thead>
            <tr>
                <th>タイトル</th>
                <th>作成者</th>
                <th>公開日時</th>
                <th>削除日時</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($notices as $notice)
                <tr>
                    <td>{{ $notice->title }}</td>
                    <td>{{ $notice->user?->name ?? '—' }}</td>
                    <td>{{ $notice->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $notice->deleted_at->format('Y-m-d H:i') }}</td>
                    <td class="row-actions">
                        <form class="inline-form" method="POST" action="{{ route('admin.notices.restore', $notice) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn secondary" type="submit">復元</button>
                        </form>
                        <form class="inline-form" method="POST" action="{{ route('admin.notices.force-delete', $notice) }}" onsubmit="return confirm('このお知らせを完全に削除しますか？元に戻せません。')">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">完全に削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">削除済みのお知らせはありません。</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 16px;">
            {{ $notices->links() }}
        </div>
    </div>
@endsection
