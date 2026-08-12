<?php

namespace Tests\Feature;

use App\Enums\NoticeStatus;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * 認可が NoticePolicy を通っていること（ミドルウェアだけで通っているのではないこと）を
 * GateEvaluated イベントで確かめる。403 そのものは他の Feature テストが固めている。
 */
class NoticeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_the_notice_index_runs_the_view_any_policy(): void
    {
        $admin = User::factory()->admin()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->get(route('admin.notices.index'))
            ->assertOk();

        $this->assertAbilityWasAllowed('viewAny');
    }

    public function test_viewing_the_trash_runs_the_view_any_policy(): void
    {
        $admin = User::factory()->admin()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->get(route('admin.notices.trashed'))
            ->assertOk();

        $this->assertAbilityWasAllowed('viewAny');
    }

    public function test_opening_the_create_form_runs_the_create_policy(): void
    {
        $admin = User::factory()->admin()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->get(route('admin.notices.create'))
            ->assertOk();

        $this->assertAbilityWasAllowed('create');
    }

    public function test_storing_a_notice_runs_the_create_policy(): void
    {
        $admin = User::factory()->admin()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => '認可の確認',
                'body' => '本文',
                'status' => NoticeStatus::Published->value,
                'published_at' => '',
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertAbilityWasAllowed('create');
    }

    public function test_editing_a_notice_runs_the_update_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->get(route('admin.notices.edit', $notice))
            ->assertOk();

        $this->assertAbilityWasAllowed('update', $notice);
    }

    public function test_updating_a_notice_runs_the_update_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => '認可の確認',
                'body' => '本文',
                'status' => NoticeStatus::Published->value,
                'published_at' => '',
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->assertAbilityWasAllowed('update', $notice);
    }

    public function test_deleting_a_notice_runs_the_delete_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertRedirect(route('admin.notices.index'));

        $this->assertAbilityWasAllowed('delete', $notice);
    }

    public function test_restoring_a_notice_runs_the_restore_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        $notice->delete();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->patch(route('admin.notices.restore', $notice))
            ->assertRedirect(route('admin.notices.trashed'));

        $this->assertAbilityWasAllowed('restore', $notice);
    }

    public function test_force_deleting_a_notice_runs_the_force_delete_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        $notice->delete();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($admin)
            ->delete(route('admin.notices.force-delete', $notice))
            ->assertRedirect(route('admin.notices.trashed'));

        $this->assertAbilityWasAllowed('forceDelete', $notice);
    }

    public function test_the_public_notice_pages_do_not_run_any_policy(): void
    {
        $notice = Notice::factory()->create();
        Event::fake([GateEvaluated::class]);

        $this->get(route('notices.index'))->assertOk();
        $this->get(route('notices.show', $notice))->assertOk();

        $this->assertFalse(
            Event::hasDispatched(GateEvaluated::class),
            '公開側では認可がまったく走らない'
        );
    }

    /**
     * 非管理者は区画の入口（admin ミドルウェア）で止まるため、Policy まで届かない。
     * 認可の一段目がミドルウェアであることの確認。
     */
    public function test_a_non_admin_is_stopped_by_the_middleware_before_the_policy_runs(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();
        Event::fake([GateEvaluated::class]);

        $this->actingAs($user)
            ->get(route('admin.notices.edit', $notice))
            ->assertForbidden();

        $this->assertFalse(
            Event::hasDispatched(GateEvaluated::class),
            'ミドルウェアが先に 403 を返すので Policy まで届かない'
        );
    }

    /**
     * 指定した能力が、その Notice に対して許可として評価されたこと。
     */
    private function assertAbilityWasAllowed(string $ability, ?Notice $notice = null): void
    {
        $matched = Event::dispatched(
            GateEvaluated::class,
            function (GateEvaluated $event) use ($ability, $notice) {
                if ($event->ability !== $ability || $event->result !== true) {
                    return false;
                }

                return $notice === null
                    || collect($event->arguments)->contains(
                        fn ($argument) => $argument instanceof Notice && $argument->is($notice)
                    );
            }
        );

        $this->assertTrue(
            $matched->isNotEmpty(),
            "{$ability} が NoticePolicy で許可として評価される"
        );
    }
}
