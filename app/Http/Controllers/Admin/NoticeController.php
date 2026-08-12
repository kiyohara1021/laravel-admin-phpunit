<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NoticeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Models\Notice;
use App\Support\NoticeFilter;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Notice::class);

        $filter = NoticeFilter::fromRequest($request);

        return view('admin.notices.index', [
            'notices' => Notice::query()
                ->with('user')
                ->search($filter->keyword)
                ->ofStatus($filter->status)
                ->latest()
                ->orderByDesc('id')
                ->paginate(10)
                // ページ送りしても絞り込み条件が消えないようにする
                ->withQueryString(),
            // ゴミ箱の件数は一覧の絞り込みとは無関係なので全件のまま
            'trashedCount' => Notice::query()->onlyTrashed()->count(),
            'filter' => $filter,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Notice::class);

        return view('admin.notices.create');
    }

    public function store(StoreNoticeRequest $request): RedirectResponse
    {
        Gate::authorize('create', Notice::class);

        // 作成者は入力ではなくログイン中の管理者から取る
        $notice = new Notice($this->noticeAttributes($request->validated()));
        $notice->user()->associate($request->user());
        $notice->save();

        return redirect()
            ->route('admin.notices.index')
            ->with('status', 'お知らせを作成しました。');
    }

    public function edit(Notice $notice): View
    {
        Gate::authorize('update', $notice);

        return view('admin.notices.edit', [
            'notice' => $notice,
        ]);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): RedirectResponse
    {
        Gate::authorize('update', $notice);

        $notice->update($this->noticeAttributes($request->validated(), $notice));

        return redirect()
            ->route('admin.notices.index')
            ->with('status', 'お知らせを更新しました。');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        Gate::authorize('delete', $notice);

        $notice->delete();

        return redirect()
            ->route('admin.notices.index')
            ->with('status', 'お知らせを削除しました。');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function noticeAttributes(array $validated, ?Notice $notice = null): array
    {
        return [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'published_at' => $this->resolvePublishedAt($validated, $notice),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvePublishedAt(array $validated, ?Notice $notice): ?CarbonInterface
    {
        return match (NoticeStatus::from($validated['status'])) {
            NoticeStatus::Draft => null,
            NoticeStatus::Scheduled => Carbon::parse($validated['published_at']),
            // すでに公開済みなら初回の公開日時を保つ（編集のたびに繰り上がらないように）
            NoticeStatus::Published => $notice?->isPublished() ? $notice->published_at : now(),
        };
    }
}
