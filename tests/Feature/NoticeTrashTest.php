<?php

namespace Tests\Feature;

use App\Enums\NoticeStatus;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_trash(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.notices.trashed'))
            ->assertOk()
            ->assertSee('ゴミ箱');
    }

    public function test_trash_only_lists_soft_deleted_notices(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create(['title' => '生きているお知らせ']);
        Notice::factory()->for($admin)->create(['title' => '捨てたお知らせ'])->delete();

        $this->actingAs($admin)
            ->get(route('admin.notices.trashed'))
            ->assertOk()
            ->assertSee('捨てたお知らせ')
            ->assertDontSee('生きているお知らせ');
    }

    public function test_trash_shows_an_empty_message_when_nothing_is_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create(['title' => '生きているお知らせ']);

        $this->actingAs($admin)
            ->get(route('admin.notices.trashed'))
            ->assertOk()
            ->assertSee('削除済みのお知らせはありません。');
    }

    public function test_admin_can_restore_a_notice_from_the_trash(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        $notice->delete();

        $this->actingAs($admin)
            ->patch(route('admin.notices.restore', $notice))
            ->assertRedirect(route('admin.notices.trashed'))
            ->assertSessionHas('status', 'お知らせを復元しました。');

        $this->assertNotSoftDeleted($notice);
    }

    public function test_restored_notice_appears_in_the_index_again(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create(['title' => '戻ってくるお知らせ']);
        $notice->delete();

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertDontSee('戻ってくるお知らせ');

        $this->actingAs($admin)
            ->patch(route('admin.notices.restore', $notice));

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee('戻ってくるお知らせ');
    }

    public function test_admin_can_permanently_delete_a_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        $notice->delete();

        $this->actingAs($admin)
            ->delete(route('admin.notices.force-delete', $notice))
            ->assertRedirect(route('admin.notices.trashed'))
            ->assertSessionHas('status', 'お知らせを完全に削除しました。');

        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
    }

    public function test_trash_orders_notices_by_deleted_at_desc(): void
    {
        $admin = User::factory()->admin()->create();
        $old = Notice::factory()->create(['title' => '古く捨てたお知らせ']);
        $new = Notice::factory()->create(['title' => '新しく捨てたお知らせ']);

        $this->travelTo(now()->subDays(3), fn () => $old->delete());
        $new->delete();

        $this->actingAs($admin)
            ->get(route('admin.notices.trashed'))
            ->assertOk()
            ->assertSeeInOrder(['新しく捨てたお知らせ', '古く捨てたお知らせ']);
    }

    public function test_trash_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();

        Notice::factory()->count(11)->create()->each->delete();

        $response = $this->actingAs($admin)
            ->get(route('admin.notices.trashed'))
            ->assertOk();

        $this->assertCount(10, $response->viewData('notices'), '1 ページ目には 10 件だけ並ぶ');
        $response->assertSee('/admin/notices/trashed?page=2', escape: false);
    }

    public function test_restore_is_not_found_for_a_notice_that_is_not_in_the_trash(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.notices.restore', $notice))
            ->assertNotFound();

        $this->assertNotSoftDeleted($notice);
    }

    public function test_force_delete_is_not_found_for_a_notice_that_is_not_in_the_trash(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create(['title' => 'まだ捨てていないお知らせ']);

        $this->actingAs($admin)
            ->delete(route('admin.notices.force-delete', $notice))
            ->assertNotFound();

        // ゴミ箱を経由しない物理削除は起きない
        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'deleted_at' => null]);
    }

    public function test_restore_and_force_delete_are_not_found_for_an_unknown_id(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.notices.restore', 999))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.notices.force-delete', 999))
            ->assertNotFound();
    }

    public function test_soft_deleted_notices_cannot_be_edited_or_deleted_from_the_admin_screens(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        $notice->delete();

        $this->actingAs($admin)
            ->get(route('admin.notices.edit', $notice))
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => 'ゴミ箱の中を編集',
                'body' => '本文です。',
                'status' => NoticeStatus::Published->value,
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertNotFound();

        $this->assertSoftDeleted($notice);
    }

    public function test_notice_index_links_to_the_trash(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee(route('admin.notices.trashed'), escape: false);
    }

    public function test_notice_index_shows_how_many_notices_are_in_the_trash(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create(['title' => '生きているお知らせ']);
        Notice::factory()->count(2)->create()->each->delete();

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertViewHas('trashedCount', 2)
            ->assertSee('ゴミ箱（2）');
    }

    public function test_notice_index_shows_zero_when_the_trash_is_empty(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertViewHas('trashedCount', 0)
            ->assertSee('ゴミ箱（0）');
    }

    public function test_non_admin_cannot_use_the_trash(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();
        $notice->delete();

        $this->actingAs($user)
            ->get(route('admin.notices.trashed'))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('admin.notices.restore', $notice))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.notices.force-delete', $notice))
            ->assertForbidden();

        $this->assertSoftDeleted($notice);
    }

    public function test_guests_are_redirected_from_the_trash(): void
    {
        $this->get(route('admin.notices.trashed'))
            ->assertRedirect(route('login'));
    }
}
