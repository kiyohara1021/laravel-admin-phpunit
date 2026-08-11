<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard_counts(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create();
        Notice::factory()->scheduled()->create();
        Notice::factory()->draft()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('ダッシュボード')
            ->assertSeeInOrder([
                'お知らせ', '4',
                '公開中', '1',
                '予約公開', '1',
                '下書き', '2',
                '管理者', '1',
            ]);
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
