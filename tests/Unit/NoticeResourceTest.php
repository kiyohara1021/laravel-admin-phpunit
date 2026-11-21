<?php

namespace Tests\Unit;

use App\Http\Resources\NoticeResource;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DB を使わず、組み立て済みのモデルから出力項目だけを確認する。
 * 公開条件・並び順・ページ送りは Feature/ApiNoticeTest 側で見る。
 */
class NoticeResourceTest extends TestCase
{
    /**
     * factory の make() は、既定のままだと作成者の User を DB に保存してしまう。
     * DB を使わないよう、作成者なしの状態から組み立てる。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function notice(array $attributes = []): Notice
    {
        return Notice::factory()->withoutAuthor()->make($attributes);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function resolve(Notice $notice): array
    {
        return (new NoticeResource($notice))->resolve(Request::create('/api/notices'));
    }

    public function test_it_exposes_exactly_the_documented_fields(): void
    {
        $notice = $this->notice(['id' => 7]);

        $this->assertSame(
            ['id', 'title', 'body', 'published_at'],
            array_keys($this->resolve($notice)),
        );
    }

    public function test_it_maps_the_notice_attributes(): void
    {
        $notice = $this->notice([
            'id' => 7,
            'title' => 'サービス開始のお知らせ',
            'body' => "1 行目\n2 行目",
            'published_at' => Carbon::parse('2026-11-01 10:00:00'),
        ]);

        $resolved = $this->resolve($notice);

        $this->assertSame(7, $resolved['id']);
        $this->assertSame('サービス開始のお知らせ', $resolved['title']);
        $this->assertSame("1 行目\n2 行目", $resolved['body'], '本文は加工せずそのまま返す');
    }

    public function test_published_at_is_an_iso_8601_string_with_an_offset(): void
    {
        $notice = $this->notice([
            'published_at' => Carbon::parse('2026-11-01 10:00:00'),
        ]);

        $publishedAt = $this->resolve($notice)['published_at'];

        $this->assertIsString($publishedAt);
        $this->assertSame($notice->published_at?->toIso8601String(), $publishedAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $publishedAt);
    }

    public function test_published_at_points_to_the_same_instant(): void
    {
        $at = Carbon::parse('2026-11-01 10:00:00');
        $notice = $this->notice(['published_at' => $at]);

        $publishedAt = $this->resolve($notice)['published_at'];

        $this->assertIsString($publishedAt);
        $this->assertTrue(Carbon::parse($publishedAt)->equalTo($at), '文字列に直しても同じ時刻を指す');
    }

    public function test_published_at_is_null_for_a_draft(): void
    {
        $notice = $this->notice(['published_at' => null]);

        $this->assertNull($this->resolve($notice)['published_at']);
    }

    public function test_it_does_not_expose_the_author_even_when_the_relation_is_loaded(): void
    {
        $author = User::factory()->admin()->make([
            'id' => 1,
            'name' => '管理 太郎',
            'email' => 'taro@example.com',
        ]);
        $notice = $this->notice(['id' => 7, 'user_id' => 1]);
        $notice->setRelation('user', $author);

        $resolved = $this->resolve($notice);

        $this->assertArrayNotHasKey('user', $resolved);
        $this->assertArrayNotHasKey('user_id', $resolved);
        $this->assertStringNotContainsString('管理 太郎', json_encode($resolved, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('taro@example.com', json_encode($resolved, JSON_THROW_ON_ERROR));
    }

    public function test_it_does_not_expose_timestamps_or_soft_delete_columns(): void
    {
        $notice = $this->notice(['id' => 7]);
        $notice->created_at = now();
        $notice->updated_at = now();
        $notice->deleted_at = now();

        $resolved = $this->resolve($notice);

        $this->assertArrayNotHasKey('created_at', $resolved);
        $this->assertArrayNotHasKey('updated_at', $resolved);
        $this->assertArrayNotHasKey('deleted_at', $resolved);
    }

    public function test_collection_wraps_each_notice_under_data_without_extra_keys(): void
    {
        $notices = Notice::factory()->withoutAuthor()->count(2)->make();

        $response = NoticeResource::collection($notices)
            ->toResponse(Request::create('/api/notices'))
            ->getData(true);

        $this->assertIsArray($response);
        $this->assertSame(['data'], array_keys($response));
        $this->assertIsArray($response['data']);
        $this->assertCount(2, $response['data']);
    }
}
