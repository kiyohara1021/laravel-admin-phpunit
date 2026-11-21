<?php

use App\Http\Controllers\Api\NoticeController;
use Illuminate\Support\Facades\Route;

// 公開 API（認証なし）。/api プレフィックスは bootstrap/app.php の withRouting が付ける
Route::get('/notices', [NoticeController::class, 'index'])->name('api.notices.index');
