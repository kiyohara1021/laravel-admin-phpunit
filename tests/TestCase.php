<?php

namespace Tests;

use App\Models\Notice;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * ビューに渡ったお知らせのページャを、要素の型まで保ったまま取り出す。
     *
     * @param  TestResponse<Response>  $response
     * @return LengthAwarePaginator<int, Notice>
     */
    protected function noticesOf(TestResponse $response): LengthAwarePaginator
    {
        $notices = $response->viewData('notices');

        $this->assertInstanceOf(LengthAwarePaginator::class, $notices);
        $this->assertContainsOnlyInstancesOf(Notice::class, $notices);

        return $notices;
    }

    /**
     * 次ページへのリンクを取り出す。次ページがなければその場で失敗させる。
     *
     * @param  TestResponse<Response>  $response
     */
    protected function nextPageUrlOf(TestResponse $response): string
    {
        $next = $this->noticesOf($response)->nextPageUrl();

        $this->assertNotNull($next, '次ページへのリンクがあること');

        return $next;
    }

    /**
     * 直前のリダイレクトに積まれたバリデーションエラーのうち、
     * 指定した項目の先頭のメッセージを返す。
     */
    protected function firstError(string $key): string
    {
        $errors = session('errors');

        $this->assertInstanceOf(ViewErrorBag::class, $errors);

        $message = $errors->getBag('default')->first($key);

        $this->assertNotSame('', $message, "{$key} のエラーメッセージが入っていない");

        return $message;
    }
}
