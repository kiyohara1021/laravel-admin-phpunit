<?php

namespace Tests\Unit;

use App\Enums\NoticeStatus;
use App\Support\NoticeFilter;
use Illuminate\Http\Request;
use Tests\TestCase;

class NoticeFilterTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $query
     */
    private function filter(array $query): NoticeFilter
    {
        return NoticeFilter::fromRequest(Request::create('/admin/notices', 'GET', $query));
    }

    public function test_it_reads_the_keyword_and_status_from_the_query_string(): void
    {
        $filter = $this->filter(['q' => '春', 'status' => 'draft']);

        $this->assertSame('春', $filter->keyword, 'q がキーワードになる');
        $this->assertSame(NoticeStatus::Draft, $filter->status, 'status が NoticeStatus になる');
        $this->assertTrue($filter->isActive(), '絞り込み中と判定される');
    }

    public function test_it_trims_the_keyword(): void
    {
        $filter = $this->filter(['q' => '  春  ']);

        $this->assertSame('春', $filter->keyword, '前後の空白は無視される');
        $this->assertTrue($filter->hasKeyword(), '空白を除いても文字が残るのでキーワードあり');
    }

    public function test_it_treats_a_blank_keyword_as_no_keyword(): void
    {
        $this->assertFalse($this->filter(['q' => ''])->hasKeyword(), '空文字は絞り込まない');
        $this->assertFalse($this->filter(['q' => '   '])->hasKeyword(), '空白だけなら絞り込まない');
        $this->assertFalse($this->filter(['q' => '   '])->isActive(), '空白だけなら絞り込み中でもない');
    }

    public function test_it_ignores_an_unknown_status(): void
    {
        $filter = $this->filter(['status' => 'archived']);

        $this->assertNull($filter->status, '存在しない状態は無視して絞り込まない');
        $this->assertFalse($filter->isActive(), '無視した結果、絞り込みなしになる');
    }

    public function test_it_ignores_a_non_string_keyword(): void
    {
        $filter = $this->filter(['q' => ['x']]);

        $this->assertSame('', $filter->keyword, '配列で来ても例外にせず空扱いにする');
        $this->assertFalse($filter->isActive(), '絞り込みなしになる');
    }

    public function test_it_ignores_a_non_string_status(): void
    {
        $filter = $this->filter(['status' => ['draft']]);

        $this->assertNull($filter->status, '配列で来ても例外にせず絞り込まない');
    }

    public function test_is_active_is_true_when_only_the_status_is_given(): void
    {
        $filter = $this->filter(['status' => 'published']);

        $this->assertFalse($filter->hasKeyword(), 'キーワードはない');
        $this->assertSame(NoticeStatus::Published, $filter->status);
        $this->assertTrue($filter->isActive(), '状態だけでも絞り込み中と判定される');
    }

    public function test_it_is_empty_without_any_query_string(): void
    {
        $filter = $this->filter([]);

        $this->assertSame('', $filter->keyword);
        $this->assertNull($filter->status);
        $this->assertFalse($filter->isActive(), '何もなければ絞り込みなし');
    }
}
