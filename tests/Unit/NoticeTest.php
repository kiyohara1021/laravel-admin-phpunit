<?php

namespace Tests\Unit;

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
    }

    public function test_draft_notice_is_not_published(): void
    {
        $notice = Notice::factory()->draft()->create();

        $this->assertFalse($notice->isPublished());
    }

    public function test_scheduled_notice_is_not_published_yet(): void
    {
        $notice = Notice::factory()->scheduled()->create();

        $this->assertFalse($notice->isPublished());
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
}
