<?php

namespace Tests\Unit;

use App\Enums\NoticeStatus;
use App\Models\Notice;
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
}
