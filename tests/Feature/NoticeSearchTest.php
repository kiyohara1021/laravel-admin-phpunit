<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // 管理画面のお知らせ一覧

    public function test_admin_index_can_be_filtered_by_a_keyword_in_the_title(): void
    {
        Notice::factory()->create(['title' => '春のメンテナンス', 'body' => '本文']);
        Notice::factory()->create(['title' => '秋のメンテナンス', 'body' => '本文']);

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('春のメンテナンス')
            ->assertDontSee('秋のメンテナンス');
    }

    public function test_admin_index_can_be_filtered_by_a_keyword_in_the_body(): void
    {
        Notice::factory()->create(['title' => 'サーバ作業のお知らせ', 'body' => '停電の予定があります']);
        Notice::factory()->create(['title' => '営業時間のお知らせ', 'body' => '通常どおりです']);

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '停電']))
            ->assertOk()
            ->assertSee('サーバ作業のお知らせ')
            ->assertDontSee('営業時間のお知らせ');
    }

    public function test_admin_index_shows_the_current_keyword_in_the_search_box(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('value="春"', escape: false);
    }

    public function test_admin_index_can_be_filtered_by_each_status(): void
    {
        Notice::factory()->create(['title' => 'いま読めるもの']);
        Notice::factory()->draft()->create(['title' => 'まだ書いているもの']);
        Notice::factory()->scheduled()->create(['title' => 'あとで出るもの']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.notices.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('まだ書いているもの')
            ->assertDontSee('いま読めるもの')
            ->assertDontSee('あとで出るもの');

        $this->actingAs($admin)
            ->get(route('admin.notices.index', ['status' => 'published']))
            ->assertOk()
            ->assertSee('いま読めるもの')
            ->assertDontSee('まだ書いているもの')
            ->assertDontSee('あとで出るもの');

        $this->actingAs($admin)
            ->get(route('admin.notices.index', ['status' => 'scheduled']))
            ->assertOk()
            ->assertSee('あとで出るもの')
            ->assertDontSee('いま読めるもの')
            ->assertDontSee('まだ書いているもの');
    }

    public function test_admin_index_marks_the_selected_status(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['status' => 'scheduled']))
            ->assertOk()
            ->assertSee('value="scheduled" selected', escape: false)
            ->assertDontSee('value="draft" selected', escape: false)
            ->assertDontSee('value="published" selected', escape: false);
    }

    public function test_admin_index_combines_the_keyword_and_the_status(): void
    {
        Notice::factory()->draft()->create(['title' => '春に書きかけのもの']);
        Notice::factory()->draft()->create(['title' => '秋に書きかけのもの']);
        Notice::factory()->create(['title' => '春に出したもの']);

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春', 'status' => 'draft']))
            ->assertOk()
            ->assertSee('春に書きかけのもの')
            ->assertDontSee('秋に書きかけのもの')
            ->assertDontSee('春に出したもの')
            ->assertSee('該当 1 件');
    }

    public function test_admin_index_ignores_an_unknown_status(): void
    {
        Notice::factory()->create(['title' => 'いま読めるもの']);
        Notice::factory()->draft()->create(['title' => 'まだ書いているもの']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('いま読めるもの')
            ->assertSee('まだ書いているもの')
            // 状態のセレクトは「すべて」に戻り、無視されたことが見て分かる
            ->assertDontSee('selected', escape: false)
            ->assertDontSee('該当');

        $this->assertCount(2, $response->viewData('notices'), '不正な状態は無視して全件を出す');
    }

    public function test_admin_index_ignores_a_blank_keyword(): void
    {
        Notice::factory()->count(2)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '   ']))
            ->assertOk()
            ->assertDontSee('該当');

        $this->assertCount(2, $response->viewData('notices'), '空白だけなら絞り込まない');
    }

    public function test_admin_index_shows_how_many_notices_matched(): void
    {
        Notice::factory()->count(2)->create(['title' => '春のメンテナンス']);
        Notice::factory()->create(['title' => '秋のメンテナンス']);

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('該当 2 件');
    }

    public function test_admin_index_shows_a_message_when_nothing_matches(): void
    {
        Notice::factory()->create(['title' => '秋のメンテナンス']);

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('検索条件に一致するお知らせはありません。')
            ->assertSee('該当 0 件');
    }

    public function test_admin_index_keeps_its_original_empty_message_without_a_filter(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee('お知らせはまだありません。')
            ->assertDontSee('検索条件に一致するお知らせはありません。');
    }

    public function test_admin_index_keeps_the_filter_in_the_pagination_links(): void
    {
        Notice::factory()->count(11)->draft()->create(['title' => 'abc のお知らせ']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => 'abc', 'status' => 'draft']))
            ->assertOk();

        $next = $response->viewData('notices')->nextPageUrl();

        $this->assertStringContainsString('q=abc', $next, '次ページのリンクがキーワードを保つ');
        $this->assertStringContainsString('status=draft', $next, '次ページのリンクが状態を保つ');
        $this->assertStringContainsString('page=2', $next, '次ページのリンクであること');
    }

    public function test_admin_index_page_two_keeps_the_filter(): void
    {
        Notice::factory()->count(11)->create(['title' => 'abc のお知らせ']);
        Notice::factory()->count(5)->create(['title' => 'xyz のお知らせ']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => 'abc', 'page' => 2]))
            ->assertOk()
            ->assertDontSee('xyz のお知らせ');

        $this->assertCount(1, $response->viewData('notices'), '2 ページ目でも条件が効いて残り 1 件だけ');
        $this->assertSame(11, $response->viewData('notices')->total(), '該当は 11 件のまま');
    }

    public function test_admin_index_treats_a_percent_sign_literally(): void
    {
        Notice::factory()->create(['title' => '達成率 100% のお知らせ', 'body' => '本文']);
        // 「100」は含むが「100%」は含まない。% がワイルドカードだと誤って当たってしまう行
        Notice::factory()->create(['title' => '達成率 100 パーセントのお知らせ', 'body' => '本文']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '100%']))
            ->assertOk()
            ->assertSee('達成率 100% のお知らせ')
            ->assertDontSee('達成率 100 パーセントのお知らせ');

        $this->assertCount(1, $response->viewData('notices'), '% がワイルドカードにならず全件にならない');
    }

    public function test_admin_index_search_does_not_show_soft_deleted_notices(): void
    {
        Notice::factory()->create(['title' => '春に残したもの']);
        Notice::factory()->create(['title' => '春に捨てたもの'])->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('春に残したもの')
            ->assertDontSee('春に捨てたもの');
    }

    /**
     * ゴミ箱の件数は「いま何件捨ててあるか」を示す案内なので、一覧の絞り込みでは減らない。
     */
    public function test_admin_index_trash_count_ignores_the_filter(): void
    {
        Notice::factory()->create(['title' => '春に残したもの']);
        Notice::factory()->create(['title' => '春に捨てたもの'])->delete();
        Notice::factory()->create(['title' => '秋に捨てたもの'])->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春', 'status' => 'published']))
            ->assertOk()
            ->assertSee('春に残したもの')
            ->assertSee('ゴミ箱（2）')
            ->assertViewHas('trashedCount', 2);
    }

    public function test_admin_index_keeps_its_order_while_filtering(): void
    {
        Notice::factory()->create(['title' => '春の 1 件目']);
        Notice::factory()->create(['title' => '春の 2 件目']);

        $this->actingAs($this->admin())
            ->get(route('admin.notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSeeInOrder(['春の 2 件目', '春の 1 件目']);
    }

    /**
     * 検索ボックスに戻すキーワードは URL から来るので、3 画面とも必ずエスケープする。
     * `{{ }}` を `{!! !!}` に変えると、ログイン不要の公開側に反射型 XSS が入る。
     */
    public function test_the_search_box_escapes_the_keyword_on_every_screen(): void
    {
        $keyword = '"><script>alert(1)</script>';

        $routes = [
            route('admin.notices.index', ['q' => $keyword]),
            route('admin.notices.trashed', ['q' => $keyword]),
            route('notices.index', ['q' => $keyword]),
        ];

        foreach ($routes as $url) {
            $this->actingAs($this->admin())
                ->get($url)
                ->assertOk()
                ->assertDontSee('<script>alert', escape: false)
                ->assertSee('value="&quot;&gt;&lt;script&gt;', escape: false);
        }
    }

    // ゴミ箱

    public function test_trash_can_be_filtered_by_a_keyword(): void
    {
        Notice::factory()->create(['title' => '春に捨てたもの'])->delete();
        Notice::factory()->create(['title' => '秋に捨てたもの'])->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['q' => '春']))
            ->assertOk()
            ->assertSee('春に捨てたもの')
            ->assertDontSee('秋に捨てたもの')
            ->assertSee('該当 1 件');
    }

    public function test_trash_can_be_filtered_by_a_keyword_in_the_body(): void
    {
        Notice::factory()->create(['title' => 'サーバ作業のお知らせ', 'body' => '停電の予定があります'])->delete();
        Notice::factory()->create(['title' => '営業時間のお知らせ', 'body' => '通常どおりです'])->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['q' => '停電']))
            ->assertOk()
            ->assertSee('サーバ作業のお知らせ')
            ->assertDontSee('営業時間のお知らせ');
    }

    public function test_trash_shows_the_current_keyword_in_the_search_box(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['q' => '春']))
            ->assertOk()
            ->assertSee('value="春"', escape: false);
    }

    public function test_trash_search_does_not_show_live_notices(): void
    {
        Notice::factory()->create(['title' => '春に残したもの']);
        Notice::factory()->create(['title' => '春に捨てたもの'])->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['q' => '春']))
            ->assertOk()
            ->assertSee('春に捨てたもの')
            ->assertDontSee('春に残したもの');
    }

    public function test_trash_shows_a_message_when_nothing_matches(): void
    {
        Notice::factory()->create(['title' => '秋に捨てたもの'])->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['q' => '春']))
            ->assertOk()
            ->assertSee('検索条件に一致するお知らせはありません。')
            ->assertDontSee('削除済みのお知らせはありません。');
    }

    public function test_trash_keeps_the_keyword_in_the_pagination_links(): void
    {
        Notice::factory()->count(11)->create(['title' => 'abc のお知らせ'])->each->delete();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['q' => 'abc']))
            ->assertOk();

        $this->assertStringContainsString(
            'q=abc',
            $response->viewData('notices')->nextPageUrl(),
            'ゴミ箱の次ページのリンクもキーワードを保つ'
        );
    }

    public function test_trash_keeps_its_deleted_at_order_while_filtering(): void
    {
        $old = Notice::factory()->create(['title' => '春に古く捨てたもの']);
        $new = Notice::factory()->create(['title' => '春に新しく捨てたもの']);

        $this->travelTo(now()->subDays(3), fn () => $old->delete());
        $new->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['q' => '春']))
            ->assertOk()
            ->assertSeeInOrder(['春に新しく捨てたもの', '春に古く捨てたもの']);
    }

    public function test_trash_ignores_a_status_parameter(): void
    {
        Notice::factory()->create(['title' => '捨てた公開のもの'])->delete();
        Notice::factory()->draft()->create(['title' => '捨てた下書きのもの'])->delete();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.notices.trashed', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('捨てた公開のもの')
            ->assertSee('捨てた下書きのもの');

        $this->assertCount(2, $response->viewData('notices'), 'ゴミ箱は状態で絞り込まない');
    }

    // 公開側の一覧

    public function test_public_index_can_be_filtered_by_a_keyword(): void
    {
        Notice::factory()->create(['title' => '春のメンテナンス', 'body' => '本文']);
        Notice::factory()->create(['title' => '秋のメンテナンス', 'body' => '本文']);

        $this->get(route('notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('春のメンテナンス')
            ->assertDontSee('秋のメンテナンス')
            ->assertSee('該当 1 件');
    }

    public function test_public_index_can_be_filtered_by_a_keyword_in_the_body(): void
    {
        Notice::factory()->create(['title' => 'サーバ作業のお知らせ', 'body' => '停電の予定があります']);
        Notice::factory()->create(['title' => '営業時間のお知らせ', 'body' => '通常どおりです']);

        $this->get(route('notices.index', ['q' => '停電']))
            ->assertOk()
            ->assertSee('サーバ作業のお知らせ')
            ->assertDontSee('営業時間のお知らせ');
    }

    public function test_public_index_shows_the_current_keyword_in_the_search_box(): void
    {
        $this->get(route('notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('value="春"', escape: false);
    }

    public function test_public_search_does_not_reveal_drafts_scheduled_or_deleted_notices(): void
    {
        Notice::factory()->create(['title' => '共通語を含む公開のもの']);
        Notice::factory()->draft()->create(['title' => '共通語を含む書きかけのもの']);
        Notice::factory()->scheduled()->create(['title' => '共通語を含むあとで出るもの']);
        Notice::factory()->create(['title' => '共通語を含む捨てたもの'])->delete();

        $response = $this->get(route('notices.index', ['q' => '共通語']))
            ->assertOk()
            ->assertSee('共通語を含む公開のもの')
            ->assertDontSee('共通語を含む書きかけのもの')
            ->assertDontSee('共通語を含むあとで出るもの')
            ->assertDontSee('共通語を含む捨てたもの');

        $this->assertCount(1, $response->viewData('notices'), '検索しても公開中の 1 件しか出ない');
    }

    public function test_public_index_ignores_a_status_parameter(): void
    {
        Notice::factory()->create(['title' => 'いま読めるもの']);
        Notice::factory()->draft()->create(['title' => 'まだ書いているもの']);

        $response = $this->get(route('notices.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('いま読めるもの')
            ->assertDontSee('まだ書いているもの');

        $this->assertCount(1, $response->viewData('notices'), '公開側は status を受け取っても公開中だけ');
    }

    public function test_public_search_keeps_the_keyword_in_the_pagination_links(): void
    {
        Notice::factory()->count(11)->create(['title' => 'abc のお知らせ']);

        $response = $this->get(route('notices.index', ['q' => 'abc']))->assertOk();

        $this->assertStringContainsString(
            'q=abc',
            $response->viewData('notices')->nextPageUrl(),
            '公開側の次ページのリンクもキーワードを保つ'
        );
    }

    public function test_public_search_shows_a_message_when_nothing_matches(): void
    {
        Notice::factory()->create(['title' => '秋のメンテナンス']);

        $this->get(route('notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSee('検索条件に一致するお知らせはありません。')
            ->assertDontSee('公開中のお知らせはありません。');
    }

    public function test_public_search_keeps_the_published_at_order(): void
    {
        Notice::factory()->create(['title' => '古い春のお知らせ', 'published_at' => now()->subDays(3)]);
        Notice::factory()->create(['title' => '新しい春のお知らせ', 'published_at' => now()->subHour()]);

        $this->get(route('notices.index', ['q' => '春']))
            ->assertOk()
            ->assertSeeInOrder(['新しい春のお知らせ', '古い春のお知らせ']);
    }

    public function test_public_search_treats_a_percent_sign_literally(): void
    {
        Notice::factory()->create(['title' => '達成率 100% のお知らせ']);
        // 「100」は含むが「100%」は含まない。% がワイルドカードだと誤って当たってしまう行
        Notice::factory()->create(['title' => '達成率 100 パーセントのお知らせ']);

        $response = $this->get(route('notices.index', ['q' => '100%']))
            ->assertOk()
            ->assertSee('達成率 100% のお知らせ')
            ->assertDontSee('達成率 100 パーセントのお知らせ');

        $this->assertCount(1, $response->viewData('notices'), '% がワイルドカードにならず全件にならない');
    }

    public function test_public_search_form_does_not_leak_the_admin_layout_words(): void
    {
        Notice::factory()->create(['title' => '春のメンテナンス']);

        $this->get(route('notices.index', ['q' => '春']))
            ->assertOk()
            ->assertDontSee('管理画面')
            ->assertDontSee('ダッシュボード')
            ->assertDontSee('としてログアウト')
            // 公開側に下書き・予約公開という語を出さない（状態のセレクトは置かない）
            ->assertDontSee('下書き')
            ->assertDontSee('予約公開');
    }
}
