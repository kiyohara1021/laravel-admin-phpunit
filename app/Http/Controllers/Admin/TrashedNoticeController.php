<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrashedNoticeController extends Controller
{
    public function index(): View
    {
        return view('admin.notices.trashed', [
            'notices' => Notice::query()
                ->onlyTrashed()
                ->with('user')
                ->latest('deleted_at')
                // 削除日時が同じときの並びを一意に決める
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function restore(string $notice): RedirectResponse
    {
        $this->trashedNotice($notice)->restore();

        return redirect()
            ->route('admin.notices.trashed')
            ->with('status', 'お知らせを復元しました。');
    }

    public function forceDelete(string $notice): RedirectResponse
    {
        $this->trashedNotice($notice)->forceDelete();

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
