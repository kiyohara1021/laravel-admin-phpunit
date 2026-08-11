<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NoticeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Models\Notice;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        return view('admin.notices.index', [
            'notices' => Notice::query()->with('user')->latest()->orderByDesc('id')->paginate(10),
            'trashedCount' => Notice::query()->onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.notices.create');
    }

    public function store(StoreNoticeRequest $request): RedirectResponse
    {
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
        return view('admin.notices.edit', [
            'notice' => $notice,
        ]);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): RedirectResponse
    {
        $notice->update($this->noticeAttributes($request->validated(), $notice));

        return redirect()
            ->route('admin.notices.index')
            ->with('status', 'お知らせを更新しました。');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
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
