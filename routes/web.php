<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NoticeController as AdminNoticeController;
use App\Http\Controllers\Admin\TrashedNoticeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NoticeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// 公開側（ログイン不要）
Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
Route::get('/notices/{notice}', [NoticeController::class, 'show'])
    ->whereNumber('notice')
    ->name('notices.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        // 固定セグメントのゴミ箱ルートを resource より先に置く
        Route::get('notices/trashed', [TrashedNoticeController::class, 'index'])
            ->name('notices.trashed');
        // 暗黙のバインディングは使わない（コントローラ側で onlyTrashed に絞る）
        Route::patch('notices/{notice}/restore', [TrashedNoticeController::class, 'restore'])
            ->whereNumber('notice')
            ->name('notices.restore');
        Route::delete('notices/{notice}/force', [TrashedNoticeController::class, 'forceDelete'])
            ->whereNumber('notice')
            ->name('notices.force-delete');

        Route::resource('notices', AdminNoticeController::class)->except(['show']);
    });
