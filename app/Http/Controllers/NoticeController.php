<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Support\NoticeFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(Request $request): View
    {
        $filter = NoticeFilter::fromRequest($request);

        return view('notices.index', [
            // 起点は必ず published()。search() は入れ子の where として後ろに足すだけなので
            // 下書き・予約公開・論理削除済みが検索から漏れ出ることはない
            'notices' => Notice::query()
                ->published()
                ->search($filter->keyword)
                // 公開日時が同じときはページ送りで取りこぼさないよう id で並びを一意にする
                ->latest('published_at')
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString(),
            'filter' => $filter,
        ]);
    }

    /**
     * 暗黙のバインディングを使わず published スコープで引く。
     * 下書き・予約公開・論理削除済み・存在しない ID がすべて同じ 404 になる。
     */
    public function show(string $notice): View
    {
        return view('notices.show', [
            'notice' => Notice::query()->published()->findOrFail($notice),
        ]);
    }
}
