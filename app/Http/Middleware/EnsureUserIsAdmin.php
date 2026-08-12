<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理画面という区画に入れるかどうかだけを見る（ダッシュボードのようにお知らせと無関係な画面も含む）。
 * 個々のお知らせに何をしてよいかは App\Policies\NoticePolicy が決める。
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 管理者の定義は User::isAdmin() に集約している
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
