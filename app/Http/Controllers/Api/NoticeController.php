<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NoticeResource;
use App\Models\Notice;
use App\Support\NoticeFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NoticeController extends Controller
{
    /**
     * 公開中のお知らせ一覧。条件と並び順は公開画面（NoticeController）と揃える。
     * 状態の絞り込み（status）は公開側では意味を持たないので受け付けない。
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filter = NoticeFilter::fromRequest($request);

        return NoticeResource::collection(
            Notice::query()
                ->published()
                ->search($filter->keyword)
                ->latest('published_at')
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString()
        );
    }
}
