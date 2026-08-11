<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'noticeCount' => Notice::query()->count(),
            'publishedCount' => Notice::query()->published()->count(),
            'scheduledCount' => Notice::query()->scheduled()->count(),
            'draftCount' => Notice::query()->draft()->count(),
            'adminCount' => User::query()->where('is_admin', true)->count(),
        ]);
    }
}
