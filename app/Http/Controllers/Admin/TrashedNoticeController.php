<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Support\NoticeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TrashedNoticeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Notice::class);

        // 状態は「削除済み」の一つしかないので、絞り込みはキーワードだけ（ofStatus は呼ばない）
        $filter = NoticeFilter::fromRequest($request);

        return view('admin.notices.trashed', [
            'notices' => Notice::query()
                ->onlyTrashed()
                ->with('user')
                ->search($filter->keyword)
                ->latest('deleted_at')
                // 削除日時が同じときの並びを一意に決める
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function restore(string $notice): RedirectResponse
    {
        $trashed = $this->trashedNotice($notice);
        Gate::authorize('restore', $trashed);

        $trashed->restore();

        return redirect()
            ->route('admin.notices.trashed')
            ->with('status', 'お知らせを復元しました。');
    }

    public function forceDelete(string $notice): RedirectResponse
    {
        $trashed = $this->trashedNotice($notice);
        Gate::authorize('forceDelete', $trashed);

        $trashed->forceDelete();

        return redirect()
            ->route('admin.notices.trashed')
            ->with('status', 'お知らせを完全に削除しました。');
    }

    /**
     * 暗黙のバインディングを使わず onlyTrashed で引く。
     * ゴミ箱に入っていないお知らせを完全削除できないようにするため。
     */
    private function trashedNotice(string $id): Notice
    {
        return Notice::query()->onlyTrashed()->findOrFail($id);
    }
}
