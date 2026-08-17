<?php

namespace Tests\Feature;

use App\Enums\NoticeStatus;
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

    public function test_notice_index_shows_each_status(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create(['title' => '公開済みのお知らせ']);
        Notice::factory()->scheduled()->create(['title' => '予約公開のお知らせ']);
        Notice::factory()->draft()->create(['title' => '下書きのお知らせ']);

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee('公開済みのお知らせ')
            ->assertSee('予約公開のお知らせ')
            ->assertSee('下書きのお知らせ')
            ->assertSee('badge published', escape: false)
            ->assertSee('badge scheduled', escape: false)
            ->assertSee('badge draft', escape: false);
    }

    public function test_admin_can_create_a_published_notice(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '公開お知らせ',
                'body' => '本文です。',
                'status' => NoticeStatus::Published->value,
            ])
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('status', 'お知らせを作成しました。');

        $this->assertDatabaseHas('notices', [
            'title' => '公開お知らせ',
            'body' => '本文です。',
        ]);

        $notice = Notice::query()->where('title', '公開お知らせ')->sole();

        $this->assertSame(NoticeStatus::Published, $notice->status());
    }

    public function test_admin_can_create_a_draft_notice(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '下書きお知らせ',
                'body' => 'まだ公開しません。',
                'status' => NoticeStatus::Draft->value,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertNull(Notice::query()->where('title', '下書きお知らせ')->value('published_at'));
    }

    public function test_admin_can_schedule_a_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $publishAt = now()->addDay()->startOfMinute();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '予約公開お知らせ',
                'body' => '明日公開します。',
                'status' => NoticeStatus::Scheduled->value,
                'published_at' => $publishAt->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('admin.notices.index'));

        $notice = Notice::query()->where('title', '予約公開お知らせ')->sole();

        $this->assertSame(NoticeStatus::Scheduled, $notice->status());
        $this->assertFalse($notice->isPublished());
        $this->assertNotNull($notice->published_at);
        $this->assertSame(
            $publishAt->format('Y-m-d H:i'),
            $notice->published_at->format('Y-m-d H:i')
        );
    }

    public function test_scheduling_requires_a_published_at(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.notices.create'))
            ->post(route('admin.notices.store'), [
                'title' => '日時なしの予約公開',
                'body' => '本文です。',
                'status' => NoticeStatus::Scheduled->value,
            ])
            ->assertRedirect(route('admin.notices.create'))
            ->assertSessionHasErrors('published_at');

        $this->assertDatabaseMissing('notices', ['title' => '日時なしの予約公開']);
    }

    public function test_scheduling_rejects_a_past_published_at(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.notices.create'))
            ->post(route('admin.notices.store'), [
                'title' => '過去日時の予約公開',
                'body' => '本文です。',
                'status' => NoticeStatus::Scheduled->value,
                'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('admin.notices.create'))
            ->assertSessionHasErrors('published_at');

        $this->assertDatabaseMissing('notices', ['title' => '過去日時の予約公開']);
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

    public function test_notice_creation_rejects_an_unknown_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.notices.create'))
            ->post(route('admin.notices.store'), [
                'title' => '不明な状態',
                'body' => '本文です。',
                'status' => 'archived',
            ])
            ->assertRedirect(route('admin.notices.create'))
            ->assertSessionHasErrors('status');
    }

    public function test_admin_can_update_a_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->draft()->create(['title' => '旧タイトル']);

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => '新タイトル',
                'body' => '更新後の本文',
                'status' => NoticeStatus::Published->value,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertDatabaseHas('notices', [
            'id' => $notice->id,
            'title' => '新タイトル',
            'body' => '更新後の本文',
        ]);

        $this->assertTrue($notice->refresh()->isPublished());
    }

    public function test_updating_a_published_notice_keeps_its_original_published_at(): void
    {
        $admin = User::factory()->admin()->create();
        $publishedAt = now()->subDays(3)->startOfSecond();
        $notice = Notice::factory()->create(['published_at' => $publishedAt]);

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => '誤字を直しました',
                'body' => '本文は変わりません。',
                'status' => NoticeStatus::Published->value,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $notice->refresh();

        $this->assertNotNull($notice->published_at);
        $this->assertSame(
            $publishedAt->format('Y-m-d H:i:s'),
            $notice->published_at->format('Y-m-d H:i:s')
        );
    }

    public function test_admin_can_move_a_published_notice_back_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => '取り下げます',
                'body' => '一度下書きに戻します。',
                'status' => NoticeStatus::Draft->value,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $notice->refresh();

        $this->assertTrue($notice->isDraft());
        $this->assertNull($notice->published_at);
    }

    public function test_admin_can_delete_a_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertRedirect(route('admin.notices.index'));

        // 論理削除なので行は残るが、通常のクエリからは消える
        $this->assertSoftDeleted($notice);
        $this->assertNull(Notice::query()->find($notice->id));
    }

    public function test_creating_a_notice_records_the_logged_in_admin_as_its_author(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '作成者つきお知らせ',
                'body' => '本文です。',
                'status' => NoticeStatus::Published->value,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $notice = Notice::query()->where('title', '作成者つきお知らせ')->sole();

        $this->assertSame($admin->id, $notice->user_id, 'ログイン中の管理者が作成者になること');
    }

    public function test_creating_a_notice_ignores_a_user_id_sent_by_the_client(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '作成者を詐称したお知らせ',
                'body' => '本文です。',
                'status' => NoticeStatus::Published->value,
                'user_id' => $other->id,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $notice = Notice::query()->where('title', '作成者を詐称したお知らせ')->sole();

        $this->assertSame($admin->id, $notice->user_id, '作成者は入力ではなくログイン中の管理者になること');
    }

    public function test_updating_a_notice_ignores_a_user_id_sent_by_the_client(): void
    {
        $author = User::factory()->admin()->create();
        $editor = User::factory()->admin()->create();
        $notice = Notice::factory()->for($author)->create();

        $this->actingAs($editor)
            ->put(route('admin.notices.update', $notice), [
                'title' => '作成者を書き換えようとした編集',
                'body' => '本文です。',
                'status' => NoticeStatus::Published->value,
                'user_id' => $editor->id,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertSame($author->id, $notice->refresh()->user_id, '入力で作成者を差し替えられないこと');
    }

    public function test_updating_a_notice_keeps_its_original_author(): void
    {
        $author = User::factory()->admin()->create();
        $editor = User::factory()->admin()->create();
        $notice = Notice::factory()->for($author)->create();

        $this->actingAs($editor)
            ->put(route('admin.notices.update', $notice), [
                'title' => '別の管理者が編集しました',
                'body' => '本文を直しました。',
                'status' => NoticeStatus::Published->value,
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertSame($author->id, $notice->refresh()->user_id, '更新しても作成者は変わらないこと');
    }

    public function test_notice_index_shows_the_author_name(): void
    {
        // 見ている人の名前はレイアウトのログアウトボタンにも出るので、作成者とは別のユーザーにする
        $author = User::factory()->admin()->create(['name' => '書いた人']);
        $viewer = User::factory()->admin()->create(['name' => '見ている人']);
        Notice::factory()->for($author)->create(['title' => '作成者を表示するお知らせ']);

        $this->actingAs($viewer)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee('作成者')
            ->assertSee('<td>書いた人</td>', escape: false);
    }

    public function test_notice_index_shows_a_dash_when_the_author_is_missing(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->withoutAuthor()->create(['title' => '作成者のないお知らせ']);

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee('作成者のないお知らせ')
            ->assertSee('<td>—</td>', escape: false);
    }

    public function test_notice_index_excludes_soft_deleted_notices(): void
    {
        $admin = User::factory()->admin()->create();
        Notice::factory()->create(['title' => '残しているお知らせ']);
        Notice::factory()->create(['title' => 'ゴミ箱に入れたお知らせ'])->delete();

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee('残しているお知らせ')
            ->assertDontSee('ゴミ箱に入れたお知らせ');
    }

    public function test_non_admin_cannot_manage_notices(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();
        $originalTitle = $notice->title;

        $this->actingAs($user)
            ->get(route('admin.notices.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.notices.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.notices.store'), [
                'title' => '不正な作成',
                'body' => '一般ユーザーは作成できません。',
                'status' => NoticeStatus::Draft->value,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.notices.update', $notice), [
                'title' => '不正な更新',
                'body' => '一般ユーザーは更新できません。',
                'status' => NoticeStatus::Draft->value,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertForbidden();

        $this->assertSame(
            $originalTitle,
            $notice->refresh()->title,
            '403 で止まるので中身は書き換わらない'
        );
    }
}
