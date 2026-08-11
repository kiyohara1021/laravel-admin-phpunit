<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_public_notice_index(): void
    {
        Notice::factory()->create(['title' => '公開中のお知らせ']);

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertSee('公開中のお知らせ');
    }

    public function test_public_index_only_lists_published_notices(): void
    {
        Notice::factory()->create(['title' => '公開したお知らせ']);
        Notice::factory()->draft()->create(['title' => '下書きのお知らせ']);
        Notice::factory()->scheduled()->create(['title' => '予約公開のお知らせ']);

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertSee('公開したお知らせ')
            ->assertDontSee('下書きのお知らせ')
            ->assertDontSee('予約公開のお知らせ');
    }

    public function test_public_index_excludes_soft_deleted_notices(): void
    {
        $notice = Notice::factory()->create(['title' => '捨てたお知らせ']);
        $notice->delete();

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertDontSee('捨てたお知らせ');
    }

    public function test_public_index_orders_notices_by_published_at_desc(): void
    {
        Notice::factory()->create([
            'title' => '古いお知らせ',
            'published_at' => now()->subDays(3),
        ]);
        Notice::factory()->create([
            'title' => '新しいお知らせ',
            'published_at' => now()->subHour(),
        ]);

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertSeeInOrder(['新しいお知らせ', '古いお知らせ']);
    }

    public function test_public_index_breaks_published_at_ties_by_id_desc(): void
    {
        $publishedAt = now()->subHour();

        Notice::factory()->create(['title' => '同時刻の 1 件目', 'published_at' => $publishedAt]);
        Notice::factory()->create(['title' => '同時刻の 2 件目', 'published_at' => $publishedAt]);
        Notice::factory()->create(['title' => '同時刻の 3 件目', 'published_at' => $publishedAt]);

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertSeeInOrder(['同時刻の 3 件目', '同時刻の 2 件目', '同時刻の 1 件目']);
    }

    public function test_public_index_is_paginated(): void
    {
        Notice::factory()->count(11)->create();

        $response = $this->get(route('notices.index'))->assertOk();

        $this->assertCount(10, $response->viewData('notices'), '1 ページ目には 10 件だけ並ぶ');
        $response->assertSee('/notices?page=2', escape: false);
    }

    public function test_pagination_does_not_repeat_or_drop_notices_published_at_the_same_time(): void
    {
        // 公開日時が全部同じでも、ページをまたいで重複・取りこぼしが起きないこと
        Notice::factory()->count(11)->create(['published_at' => now()->subHour()]);

        $first = $this->get(route('notices.index'))->assertOk();
        $second = $this->get(route('notices.index', ['page' => 2]))->assertOk();

        $ids = $first->viewData('notices')->pluck('id')
            ->merge($second->viewData('notices')->pluck('id'));

        $this->assertSame(11, $ids->unique()->count(), '2 ページ合わせて 11 件すべてが 1 度ずつ出ること');
    }

    public function test_public_index_shows_a_message_when_there_is_nothing_to_show(): void
    {
        Notice::factory()->draft()->create();

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertSee('公開中のお知らせはありません。');
    }

    public function test_guests_can_view_a_published_notice(): void
    {
        $notice = Notice::factory()->create([
            'title' => 'サービス開始のお知らせ',
            'body' => '本日からサービスを開始しました。',
        ]);

        $this->get(route('notices.show', $notice))
            ->assertOk()
            ->assertSee('サービス開始のお知らせ')
            ->assertSee('本日からサービスを開始しました。');
    }

    public function test_draft_notice_is_not_found_on_the_public_side(): void
    {
        $notice = Notice::factory()->draft()->create();

        $this->get(route('notices.show', $notice))->assertNotFound();
    }

    public function test_scheduled_notice_is_not_found_on_the_public_side(): void
    {
        $notice = Notice::factory()->scheduled()->create();

        $this->get(route('notices.show', $notice))->assertNotFound();
    }

    public function test_soft_deleted_notice_is_not_found_on_the_public_side(): void
    {
        $notice = Notice::factory()->create(['title' => '捨てたお知らせ']);
        $notice->delete();

        $this->get(route('notices.show', $notice))
            ->assertNotFound()
            ->assertDontSee('捨てたお知らせ');
    }

    public function test_force_deleted_notice_is_not_found_on_the_public_side(): void
    {
        $notice = Notice::factory()->create();
        $notice->delete();

        $soft = $this->get(route('notices.show', $notice))->assertNotFound();

        $notice->forceDelete();

        $forced = $this->get(route('notices.show', $notice))->assertNotFound();

        $this->assertSame(
            $soft->getContent(),
            $forced->getContent(),
            '論理削除済みと完全削除済みで応答が変わらない（存在が漏れない）'
        );
    }

    public function test_public_notice_body_is_escaped(): void
    {
        $notice = Notice::factory()->create([
            'body' => "<script>alert('x')</script>",
        ]);

        $this->get(route('notices.show', $notice))
            ->assertOk()
            ->assertDontSee('<script>alert', escape: false)
            ->assertSee('&lt;script&gt;', escape: false);
    }

    public function test_public_notice_body_keeps_line_breaks(): void
    {
        $notice = Notice::factory()->create([
            'body' => "1 行目\n2 行目",
        ]);

        $this->get(route('notices.show', $notice))
            ->assertOk()
            ->assertSee('<br', escape: false);
    }

    public function test_public_pages_do_not_use_the_admin_layout(): void
    {
        $notice = Notice::factory()->create();

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertDontSee('管理画面')
            ->assertDontSee('ダッシュボード')
            ->assertDontSee('としてログアウト');

        $this->get(route('notices.show', $notice))
            ->assertOk()
            ->assertDontSee('管理画面')
            ->assertDontSee('ダッシュボード')
            ->assertDontSee('としてログアウト');
    }

    public function test_logged_in_admin_can_also_view_the_public_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->for($admin)->create(['title' => '公開中のお知らせ']);

        $this->actingAs($admin)
            ->get(route('notices.index'))
            ->assertOk()
            ->assertSee('公開中のお知らせ');

        $this->actingAs($admin)
            ->get(route('notices.show', $notice))
            ->assertOk()
            ->assertSee('公開中のお知らせ');
    }

    public function test_public_pages_do_not_show_the_author(): void
    {
        $admin = User::factory()->admin()->create(['name' => '管理 太郎']);
        $notice = Notice::factory()->for($admin)->create();

        $this->get(route('notices.index'))
            ->assertOk()
            ->assertDontSee('管理 太郎');

        $this->get(route('notices.show', $notice))
            ->assertOk()
            ->assertDontSee('管理 太郎');
    }

    public function test_root_still_redirects_to_the_admin_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('admin.dashboard'));
    }
}
