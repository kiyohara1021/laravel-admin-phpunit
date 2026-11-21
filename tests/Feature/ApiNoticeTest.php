<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ApiNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_fetch_the_notice_list_as_json(): void
    {
        Notice::factory()->create(['title' => '公開中のお知らせ']);

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', '公開中のお知らせ');
    }

    public function test_the_api_lives_under_the_api_prefix(): void
    {
        $this->assertSame('/api/notices', route('api.notices.index', absolute: false));
    }

    public function test_each_notice_exposes_only_the_documented_fields(): void
    {
        $publishedAt = now()->subHour();
        $notice = Notice::factory()->create([
            'title' => 'サービス開始のお知らせ',
            'body' => '本日からサービスを開始しました。',
            'published_at' => $publishedAt,
        ]);

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id' => $notice->id,
                'title' => 'サービス開始のお知らせ',
                'body' => '本日からサービスを開始しました。',
                'published_at' => $publishedAt->toIso8601String(),
            ]);
    }

    public function test_the_response_has_data_links_and_meta(): void
    {
        Notice::factory()->create();

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data', 1)
                ->has('links')
                ->has('meta', fn (AssertableJson $meta) => $meta
                    ->where('current_page', 1)
                    ->where('per_page', 10)
                    ->where('total', 1)
                    ->etc()));
    }

    public function test_it_only_lists_published_notices(): void
    {
        Notice::factory()->create(['title' => '公開したお知らせ']);
        Notice::factory()->draft()->create(['title' => '下書きのお知らせ']);
        Notice::factory()->scheduled()->create(['title' => '予約公開のお知らせ']);

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '公開したお知らせ')
            ->assertDontSee('下書きのお知らせ')
            ->assertDontSee('予約公開のお知らせ');
    }

    public function test_it_excludes_soft_deleted_notices(): void
    {
        $notice = Notice::factory()->create(['title' => '捨てたお知らせ']);
        $notice->delete();

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertDontSee('捨てたお知らせ');
    }

    public function test_it_orders_notices_by_published_at_desc(): void
    {
        Notice::factory()->create([
            'title' => '古いお知らせ',
            'published_at' => now()->subDays(3),
        ]);
        Notice::factory()->create([
            'title' => '新しいお知らせ',
            'published_at' => now()->subHour(),
        ]);

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', '新しいお知らせ')
            ->assertJsonPath('data.1.title', '古いお知らせ');
    }

    public function test_it_breaks_published_at_ties_by_id_desc(): void
    {
        $publishedAt = now()->subHour();

        $first = Notice::factory()->create(['published_at' => $publishedAt]);
        $second = Notice::factory()->create(['published_at' => $publishedAt]);
        $third = Notice::factory()->create(['published_at' => $publishedAt]);

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$third->id, $second->id, $first->id]);
    }

    public function test_it_is_paginated_by_ten(): void
    {
        Notice::factory()->count(11)->create();

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson(route('api.notices.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_pagination_does_not_repeat_or_drop_notices_published_at_the_same_time(): void
    {
        Notice::factory()->count(11)->create(['published_at' => now()->subHour()]);

        $first = $this->getJson(route('api.notices.index'))->assertOk();
        $second = $this->getJson(route('api.notices.index', ['page' => 2]))->assertOk();

        $ids = $first->collect('data.*.id')->merge($second->collect('data.*.id'));

        $this->assertSame(11, $ids->unique()->count(), '2 ページ合わせて 11 件すべてが 1 度ずつ出ること');
    }

    public function test_a_page_past_the_end_is_empty_not_an_error(): void
    {
        Notice::factory()->create();

        $this->getJson(route('api.notices.index', ['page' => 99]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_returns_an_empty_list_when_there_is_nothing_to_show(): void
    {
        Notice::factory()->draft()->create();

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('links.next', null);
    }

    public function test_it_can_be_filtered_by_a_keyword_in_the_title(): void
    {
        Notice::factory()->create(['title' => '春のメンテナンス', 'body' => '本文']);
        Notice::factory()->create(['title' => '秋のメンテナンス', 'body' => '本文']);

        $this->getJson(route('api.notices.index', ['q' => '春']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '春のメンテナンス');
    }

    public function test_it_can_be_filtered_by_a_keyword_in_the_body(): void
    {
        Notice::factory()->create(['title' => 'サーバ作業のお知らせ', 'body' => '停電の予定があります']);
        Notice::factory()->create(['title' => '営業時間のお知らせ', 'body' => '通常どおりです']);

        $this->getJson(route('api.notices.index', ['q' => '停電']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'サーバ作業のお知らせ');
    }

    public function test_keyword_search_never_leaks_non_public_notices(): void
    {
        Notice::factory()->create(['title' => '共通ワード 公開']);
        Notice::factory()->draft()->create(['title' => '共通ワード 下書き']);
        Notice::factory()->scheduled()->create(['title' => '共通ワード 予約']);
        Notice::factory()->create(['title' => '共通ワード 削除済み'])->delete();

        $this->getJson(route('api.notices.index', ['q' => '共通ワード']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '共通ワード 公開');
    }

    public function test_like_wildcards_in_the_keyword_are_treated_literally(): void
    {
        Notice::factory()->create(['title' => '100% 達成']);
        Notice::factory()->create(['title' => '別のお知らせ']);

        $this->getJson(route('api.notices.index', ['q' => '%']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '100% 達成');
    }

    public function test_pagination_links_keep_the_keyword(): void
    {
        Notice::factory()->count(11)->create(['title' => '春のお知らせ']);

        $next = $this->getJson(route('api.notices.index', ['q' => '春']))
            ->assertOk()
            ->json('links.next');

        $this->assertIsString($next);
        $this->assertStringContainsString('q='.rawurlencode('春'), $next);
    }

    public function test_invalid_query_values_are_ignored_not_errors(): void
    {
        Notice::factory()->create(['title' => '公開中のお知らせ']);

        // 配列で来た q も、存在しない状態も、絞り込みなしとして扱う（500 や 422 にしない）
        $this->getJson('/api/notices?q[]=x&status=nonsense')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_status_filter_cannot_expose_drafts_or_scheduled_notices(): void
    {
        Notice::factory()->create(['title' => '公開したお知らせ']);
        Notice::factory()->draft()->create(['title' => '下書きのお知らせ']);
        Notice::factory()->scheduled()->create(['title' => '予約公開のお知らせ']);

        foreach (['draft', 'scheduled', 'published'] as $status) {
            $this->getJson(route('api.notices.index', ['status' => $status]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.title', '公開したお知らせ');
        }
    }

    public function test_it_does_not_expose_the_author(): void
    {
        $admin = User::factory()->admin()->create(['name' => '管理 太郎', 'email' => 'taro@example.com']);
        Notice::factory()->for($admin)->create();

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.user')
            ->assertDontSee('管理 太郎')
            ->assertDontSee('taro@example.com');
    }

    public function test_it_works_for_a_logged_in_admin_too(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->for($admin)->create(['title' => '公開中のお知らせ']);

        $this->actingAs($admin)
            ->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', '公開中のお知らせ');
    }

    public function test_only_get_is_allowed(): void
    {
        $this->postJson(route('api.notices.index'), ['title' => 'x', 'body' => 'y'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseCount('notices', 0);
    }

    public function test_unknown_api_paths_return_json_404_not_an_html_page(): void
    {
        $this->get('/api/unknown')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_the_api_does_not_start_a_session_or_set_cookies(): void
    {
        Notice::factory()->create();

        $this->getJson(route('api.notices.index'))
            ->assertOk()
            ->assertCookieMissing('laravel_session')
            ->assertCookieMissing('XSRF-TOKEN');
    }
}
