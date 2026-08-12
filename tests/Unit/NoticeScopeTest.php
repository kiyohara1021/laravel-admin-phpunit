<?php

namespace Tests\Unit;

use App\Enums\NoticeStatus;
use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_a_keyword_in_the_title(): void
    {
        Notice::factory()->create(['title' => '春のメンテナンス', 'body' => '本文']);
        Notice::factory()->create(['title' => '秋のメンテナンス', 'body' => '本文']);

        $titles = Notice::query()->search('春')->pluck('title');

        $this->assertTrue($titles->contains('春のメンテナンス'), 'タイトルの部分一致で当たる');
        $this->assertFalse($titles->contains('秋のメンテナンス'), '一致しないものは返らない');
    }

    public function test_search_matches_a_keyword_in_the_body(): void
    {
        Notice::factory()->create(['title' => 'お知らせ A', 'body' => '停電の予定があります']);
        Notice::factory()->create(['title' => 'お知らせ B', 'body' => '通常営業です']);

        $titles = Notice::query()->search('停電')->pluck('title');

        $this->assertTrue($titles->contains('お知らせ A'), '本文の部分一致でも当たる');
        $this->assertFalse($titles->contains('お知らせ B'), '一致しないものは返らない');
    }

    public function test_search_is_a_partial_match(): void
    {
        Notice::factory()->create(['title' => 'サービス再開のお知らせ', 'body' => '本文']);

        $this->assertSame(
            1,
            Notice::query()->search('ビス再')->count(),
            '語の途中でも部分一致する'
        );
    }

    public function test_search_returns_everything_when_the_keyword_is_empty_or_blank(): void
    {
        Notice::factory()->count(3)->create();

        $this->assertSame(3, Notice::query()->search('')->count(), '空文字なら絞り込まない');
        $this->assertSame(3, Notice::query()->search('   ')->count(), '空白だけでも絞り込まない');
        $this->assertSame(3, Notice::query()->search(null)->count(), 'null でも絞り込まない');
    }

    public function test_search_ignores_surrounding_spaces(): void
    {
        Notice::factory()->create(['title' => '春のメンテナンス']);
        Notice::factory()->create(['title' => '秋のメンテナンス']);

        $this->assertSame(
            Notice::query()->search('春')->pluck('title')->all(),
            Notice::query()->search('  春  ')->pluck('title')->all(),
            '前後の空白があっても結果は同じ'
        );
    }

    public function test_search_treats_a_percent_sign_as_a_literal(): void
    {
        Notice::factory()->create(['title' => '達成率 100% のお知らせ', 'body' => '本文']);
        Notice::factory()->create(['title' => '達成率 100 パーセントのお知らせ', 'body' => '本文']);
        Notice::factory()->create(['title' => '無関係なお知らせ', 'body' => '本文']);

        $titles = Notice::query()->search('100%')->pluck('title');

        $this->assertCount(1, $titles, '% がワイルドカードとして働かず、全件にならない');
        $this->assertSame('達成率 100% のお知らせ', $titles->first(), 'リテラルの 100% を含む 1 件だけが返る');
    }

    public function test_searching_for_a_percent_sign_alone_does_not_return_everything(): void
    {
        Notice::factory()->count(3)->create(['title' => '記号を含まないお知らせ', 'body' => '本文']);

        $this->assertSame(
            0,
            Notice::query()->search('%')->count(),
            '% だけで検索しても全件にはならない'
        );
    }

    public function test_search_treats_an_underscore_as_a_literal(): void
    {
        Notice::factory()->create(['title' => 'a_b の話', 'body' => '本文']);
        Notice::factory()->create(['title' => 'axb の話', 'body' => '本文']);

        $titles = Notice::query()->search('a_b')->pluck('title');

        $this->assertCount(1, $titles, '_ が任意の 1 文字として働かない');
        $this->assertSame('a_b の話', $titles->first(), 'リテラルの _ を含む 1 件だけが返る');
    }

    public function test_search_treats_a_backslash_as_a_literal(): void
    {
        Notice::factory()->create(['title' => 'C:\\Users の話', 'body' => '本文']);
        Notice::factory()->create(['title' => 'C:/Users の話', 'body' => '本文']);

        $titles = Notice::query()->search('C:\\Users')->pluck('title');

        $this->assertCount(1, $titles, 'バックスラッシュ自体も検索できる');
        $this->assertSame('C:\\Users の話', $titles->first(), 'エスケープ記号が壊れていない');
    }

    /**
     * エスケープ記号そのものを含む語も普通に検索できること。
     * escapeLike が `!` を二重化し忘れると、`!` が後ろの 1 文字を食って一致しなくなる。
     */
    public function test_search_treats_the_escape_character_as_a_literal(): void
    {
        Notice::factory()->create(['title' => '注意! のお知らせ', 'body' => '本文']);
        Notice::factory()->create(['title' => '注意 のお知らせ', 'body' => '本文']);

        $titles = Notice::query()->search('注意!')->pluck('title');

        $this->assertCount(1, $titles, 'エスケープ記号を含む語でも一致する');
        $this->assertSame('注意! のお知らせ', $titles->first(), 'リテラルの ! を含む 1 件だけが返る');
    }

    public function test_search_does_not_leak_past_the_published_scope(): void
    {
        Notice::factory()->create(['title' => '共通語を含む公開のお知らせ']);
        Notice::factory()->draft()->create(['title' => '共通語を含む下書きのお知らせ']);
        Notice::factory()->scheduled()->create(['title' => '共通語を含む予約公開のお知らせ']);
        Notice::factory()->create(['title' => '共通語を含む削除済みのお知らせ'])->delete();

        $titles = Notice::query()->published()->search('共通語')->pluck('title');

        $this->assertCount(1, $titles, 'OR がスコープ外に漏れず、公開中の 1 件だけになる');
        $this->assertSame('共通語を含む公開のお知らせ', $titles->first());
    }

    public function test_search_groups_its_or_condition(): void
    {
        $sql = Notice::query()->published()->search('春')->toSql();

        $this->assertStringContainsString(
            '(title like ? escape',
            $sql,
            'OR は括弧で囲まれ、published() の条件の外に漏れない'
        );
        $this->assertStringContainsString(
            "escape '!'",
            $sql,
            'ESCAPE 句を明示している（% や _ をリテラルとして扱うため）'
        );
    }

    /**
     * エスケープ記号にバックスラッシュを使うと SQL に `escape '\'` という並びができ、
     * toRawSql() が閉じ引用符を読み飛ばして以降の値を ? のまま残す（実行結果は正しいが調査で誤読する）。
     */
    public function test_search_does_not_break_raw_sql_dumps(): void
    {
        $sql = Notice::query()
            ->published()
            ->search('春')
            ->where('user_id', 1)
            ->toRawSql();

        $this->assertStringNotContainsString(
            '?',
            $sql,
            'toRawSql() にプレースホルダが残らない（エスケープ記号が文字列リテラル判定を壊さない）'
        );
        $this->assertStringContainsString(
            "'user_id' = 1",
            str_replace('"', "'", $sql),
            'search() の後ろに足した条件の値も正しく埋め込まれる'
        );
    }

    public function test_search_excludes_soft_deleted_notices(): void
    {
        Notice::factory()->create(['title' => '残しているお知らせ']);
        Notice::factory()->create(['title' => '捨てたお知らせ'])->delete();

        $titles = Notice::query()->search('お知らせ')->pluck('title');

        $this->assertTrue($titles->contains('残しているお知らせ'));
        $this->assertFalse($titles->contains('捨てたお知らせ'), '論理削除済みは検索に出てこない');
    }

    public function test_search_finds_soft_deleted_notices_only_through_only_trashed(): void
    {
        Notice::factory()->create(['title' => '生きているお知らせ']);
        Notice::factory()->create(['title' => '捨てたお知らせ'])->delete();

        $titles = Notice::query()->onlyTrashed()->search('お知らせ')->pluck('title');

        $this->assertTrue($titles->contains('捨てたお知らせ'), 'ゴミ箱では削除済みが検索できる');
        $this->assertFalse($titles->contains('生きているお知らせ'), '生存中は混ざらない');
    }

    public function test_of_status_filters_by_draft_published_and_scheduled(): void
    {
        Notice::factory()->create(['title' => '公開のお知らせ']);
        Notice::factory()->draft()->create(['title' => '下書きのお知らせ']);
        Notice::factory()->scheduled()->create(['title' => '予約公開のお知らせ']);

        $this->assertSame(
            ['下書きのお知らせ'],
            Notice::query()->ofStatus(NoticeStatus::Draft)->pluck('title')->all(),
            '下書きだけが返る'
        );
        $this->assertSame(
            ['公開のお知らせ'],
            Notice::query()->ofStatus(NoticeStatus::Published)->pluck('title')->all(),
            '公開だけが返る'
        );
        $this->assertSame(
            ['予約公開のお知らせ'],
            Notice::query()->ofStatus(NoticeStatus::Scheduled)->pluck('title')->all(),
            '予約公開だけが返る'
        );
    }

    public function test_of_status_returns_everything_when_no_status_is_given(): void
    {
        Notice::factory()->create();
        Notice::factory()->draft()->create();
        Notice::factory()->scheduled()->create();

        $this->assertSame(3, Notice::query()->ofStatus(null)->count(), 'null なら絞り込まない');
    }

    public function test_of_status_keeps_soft_deleted_notices_out(): void
    {
        Notice::factory()->draft()->create(['title' => '残した下書き']);
        Notice::factory()->draft()->create(['title' => '捨てた下書き'])->delete();

        $titles = Notice::query()->ofStatus(NoticeStatus::Draft)->pluck('title');

        $this->assertTrue($titles->contains('残した下書き'));
        $this->assertFalse($titles->contains('捨てた下書き'), '論理削除済みは状態で絞っても出てこない');
    }

    public function test_search_and_of_status_can_be_combined(): void
    {
        Notice::factory()->draft()->create(['title' => '春の下書き']);
        Notice::factory()->draft()->create(['title' => '秋の下書き']);
        Notice::factory()->create(['title' => '春の公開']);

        $titles = Notice::query()->search('春')->ofStatus(NoticeStatus::Draft)->pluck('title');

        $this->assertCount(1, $titles, 'キーワードと状態の両方を満たす 1 件だけ');
        $this->assertSame('春の下書き', $titles->first());
    }

    public function test_search_does_not_change_the_order(): void
    {
        Notice::factory()->create(['title' => '古い春のお知らせ', 'published_at' => now()->subDays(3)]);
        Notice::factory()->create(['title' => '新しい春のお知らせ', 'published_at' => now()->subHour()]);

        $titles = Notice::query()
            ->published()
            ->search('春')
            ->latest('published_at')
            ->orderByDesc('id')
            ->pluck('title');

        $this->assertSame(
            ['新しい春のお知らせ', '古い春のお知らせ'],
            $titles->all(),
            '絞り込んでも並び順は変わらない'
        );
    }
}
