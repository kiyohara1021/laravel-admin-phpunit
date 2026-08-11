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

        // 数字だけを探すとレイアウトの別の箇所に当たるので、件数はマークアップごと見る
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('ダッシュボード')
            ->assertViewHas('noticeCount', 4)
            ->assertViewHas('publishedCount', 1)
            ->assertViewHas('scheduledCount', 1)
            ->assertViewHas('draftCount', 2)
            ->assertViewHas('adminCount', 1)
            ->assertSeeInOrder([
                'お知らせ', '<strong>4</strong>',
                '公開中', '<strong>1</strong>',
                '予約公開', '<strong>1</strong>',
                '下書き', '<strong>2</strong>',
                '管理者', '<strong>1</strong>',
            ], escape: false);
    }

    public function test_dashboard_counts_exclude_soft_deleted_notices(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create();
        Notice::factory()->create()->delete();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('noticeCount', 1)
            ->assertViewHas('publishedCount', 1)
            ->assertViewHas('scheduledCount', 0)
            ->assertViewHas('draftCount', 0)
            ->assertSeeInOrder([
                'お知らせ', '<strong>1</strong>',
                '公開中', '<strong>1</strong>',
                '予約公開', '<strong>0</strong>',
                '下書き', '<strong>0</strong>',
            ], escape: false);
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
