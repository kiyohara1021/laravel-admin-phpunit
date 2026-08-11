<?php

namespace Tests\Unit;

use App\Enums\NoticeStatus;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_is_published_when_published_at_is_in_the_past(): void
    {
        $notice = Notice::factory()->create([
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue($notice->isPublished());
        $this->assertSame(NoticeStatus::Published, $notice->status());
    }

    public function test_draft_notice_is_not_published(): void
    {
        $notice = Notice::factory()->draft()->create();

        $this->assertFalse($notice->isPublished());
        $this->assertTrue($notice->isDraft());
        $this->assertSame(NoticeStatus::Draft, $notice->status());
    }

    public function test_scheduled_notice_is_not_published_yet(): void
    {
        $notice = Notice::factory()->scheduled()->create();

        $this->assertFalse($notice->isPublished());
        $this->assertTrue($notice->isScheduled());
        $this->assertSame(NoticeStatus::Scheduled, $notice->status());
    }

    public function test_published_scope_excludes_drafts_and_future_notices(): void
    {
        Notice::factory()->create(['title' => '公開済み']);
        Notice::factory()->draft()->create(['title' => '下書き']);
        Notice::factory()->scheduled()->create(['title' => '予約公開']);

        $published = Notice::query()->published()->pluck('title');

        $this->assertTrue($published->contains('公開済み'));
        $this->assertFalse($published->contains('下書き'));
        $this->assertFalse($published->contains('予約公開'));
    }

    public function test_scheduled_scope_only_returns_future_notices(): void
    {
        Notice::factory()->create(['title' => '公開済み']);
        Notice::factory()->draft()->create(['title' => '下書き']);
        Notice::factory()->scheduled()->create(['title' => '予約公開']);

        $scheduled = Notice::query()->scheduled()->pluck('title');

        $this->assertTrue($scheduled->contains('予約公開'));
        $this->assertFalse($scheduled->contains('公開済み'));
        $this->assertFalse($scheduled->contains('下書き'));
    }

    public function test_draft_scope_only_returns_notices_without_a_published_at(): void
    {
        Notice::factory()->create(['title' => '公開済み']);
        Notice::factory()->draft()->create(['title' => '下書き']);
        Notice::factory()->scheduled()->create(['title' => '予約公開']);

        $draft = Notice::query()->draft()->pluck('title');

        $this->assertTrue($draft->contains('下書き'));
        $this->assertFalse($draft->contains('公開済み'));
        $this->assertFalse($draft->contains('予約公開'));
    }

    public function test_every_notice_belongs_to_exactly_one_status_scope(): void
    {
        Notice::factory()->create();
        Notice::factory()->draft()->create();
        Notice::factory()->scheduled()->create();

        $total = Notice::query()->count();
        $split = Notice::query()->published()->count()
            + Notice::query()->scheduled()->count()
            + Notice::query()->draft()->count();

        $this->assertSame($total, $split);
    }

    public function test_deleting_a_notice_only_sets_deleted_at(): void
    {
        $notice = Notice::factory()->create(['title' => '消したお知らせ']);

        $notice->delete();

        $this->assertNull(Notice::query()->find($notice->id), '通常のクエリからは消えていること');

        $trashed = Notice::withTrashed()->find($notice->id);

        $this->assertNotNull($trashed, '行そのものは残っていること');
        $this->assertNotNull($trashed->deleted_at, 'deleted_at が入っていること');
        $this->assertSame('消したお知らせ', $trashed->title);
    }

    public function test_soft_deleted_notices_are_excluded_from_every_status_scope(): void
    {
        Notice::factory()->create()->delete();
        Notice::factory()->scheduled()->create()->delete();
        Notice::factory()->draft()->create()->delete();

        $this->assertSame(0, Notice::query()->published()->count(), '公開中に削除済みが混ざらないこと');
        $this->assertSame(0, Notice::query()->scheduled()->count(), '予約公開に削除済みが混ざらないこと');
        $this->assertSame(0, Notice::query()->draft()->count(), '下書きに削除済みが混ざらないこと');
    }

    public function test_status_scopes_still_add_up_to_the_total_after_a_soft_delete(): void
    {
        Notice::factory()->create();
        Notice::factory()->scheduled()->create();
        Notice::factory()->draft()->create();
        Notice::factory()->create()->delete();

        $total = Notice::query()->count();
        $split = Notice::query()->published()->count()
            + Notice::query()->scheduled()->count()
            + Notice::query()->draft()->count();

        $this->assertSame(3, $total, '削除済みは総数に含まれないこと');
        $this->assertSame($total, $split, '3 つの状態の合計が総数と一致すること');
    }

    public function test_only_trashed_returns_just_the_deleted_notices(): void
    {
        Notice::factory()->create(['title' => '生きているお知らせ']);
        Notice::factory()->create(['title' => '削除済みのお知らせ'])->delete();

        $trashed = Notice::query()->onlyTrashed()->pluck('title');

        $this->assertTrue($trashed->contains('削除済みのお知らせ'), '削除済みが取得できること');
        $this->assertFalse($trashed->contains('生きているお知らせ'), '削除していないものは含まれないこと');
    }

    public function test_restoring_a_notice_brings_it_back_to_its_status_scope(): void
    {
        $notice = Notice::factory()->create(['title' => '復元するお知らせ']);
        $notice->delete();

        $this->assertSame(0, Notice::query()->published()->count(), '削除中は公開中に出てこないこと');

        $notice->restore();

        $this->assertNull($notice->fresh()->deleted_at, 'deleted_at が null に戻ること');
        $this->assertTrue(
            Notice::query()->published()->pluck('title')->contains('復元するお知らせ'),
            '復元すると公開中に戻ること'
        );
    }

    public function test_notice_belongs_to_its_author(): void
    {
        $author = User::factory()->create(['name' => '書いた人']);
        $notice = Notice::factory()->for($author)->create();

        $this->assertInstanceOf(User::class, $notice->user);
        $this->assertSame($author->id, $notice->user->id, '作成者が取得できること');
        $this->assertSame('書いた人', $notice->user->name);
    }

    public function test_notice_without_an_author_has_a_null_user(): void
    {
        $notice = Notice::factory()->withoutAuthor()->create();

        $this->assertNull($notice->user_id, '作成者なしで保存できること');
        $this->assertNull($notice->user, 'リレーションが null になること');
    }

    public function test_deleting_the_author_keeps_the_notice_and_nulls_the_user_id(): void
    {
        $author = User::factory()->create();
        $notice = Notice::factory()->for($author)->create(['title' => '作成者を消したお知らせ']);

        $author->delete();

        $notice = $notice->fresh();

        $this->assertNotNull($notice, 'ユーザーを削除してもお知らせは残ること');
        $this->assertNull($notice->user_id, 'user_id が null になること');
        $this->assertSame('作成者を消したお知らせ', $notice->title);
    }
}
