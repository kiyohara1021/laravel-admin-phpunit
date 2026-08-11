<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_notice_index(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create(['title' => '春のメンテナンス']);

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee('春のメンテナンス')
            ->assertSee($notice->title);
    }

    public function test_admin_can_create_a_published_notice(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '公開お知らせ',
                'body' => '本文です。',
                'published' => '1',
            ])
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('status', 'お知らせを作成しました。');

        $this->assertDatabaseHas('notices', [
            'title' => '公開お知らせ',
            'body' => '本文です。',
        ]);

        $this->assertNotNull(Notice::query()->where('title', '公開お知らせ')->value('published_at'));
    }

    public function test_admin_can_create_a_draft_notice(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '下書きお知らせ',
                'body' => 'まだ公開しません。',
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertNull(Notice::query()->where('title', '下書きお知らせ')->value('published_at'));
    }

    public function test_notice_creation_requires_title_and_body(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.notices.create'))
            ->post(route('admin.notices.store'), [
                'title' => '',
                'body' => '',
            ])
            ->assertRedirect(route('admin.notices.create'))
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_admin_can_update_a_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->draft()->create(['title' => '旧タイトル']);

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => '新タイトル',
                'body' => '更新後の本文',
                'published' => '1',
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertDatabaseHas('notices', [
            'id' => $notice->id,
            'title' => '新タイトル',
            'body' => '更新後の本文',
        ]);

        $this->assertTrue($notice->fresh()->isPublished());
    }

    public function test_admin_can_delete_a_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertRedirect(route('admin.notices.index'));

        $this->assertDatabaseMissing('notices', [
            'id' => $notice->id,
        ]);
    }

    public function test_non_admin_cannot_manage_notices(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.notices.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.notices.store'), [
                'title' => '不正な作成',
                'body' => '一般ユーザーは作成できません。',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertForbidden();
    }
}
