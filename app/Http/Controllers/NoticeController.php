<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        return view('notices.index', [
            // 公開日時が同じときはページ送りで取りこぼさないよう id で並びを一意にする
            'notices' => Notice::query()
                ->published()
                ->latest('published_at')
                ->orderByDesc('id')
                ->paginate(10),
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
