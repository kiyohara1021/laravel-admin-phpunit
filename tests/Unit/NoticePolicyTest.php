<?php

namespace Tests\Unit;

use App\Models\Notice;
use App\Models\User;
use App\Policies\NoticePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class NoticePolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * お知らせ 1 件に対する能力（$notice を引数に取るもの）
     *
     * @var list<string>
     */
    private const NOTICE_ABILITIES = ['update', 'delete', 'restore', 'forceDelete'];

    /**
     * お知らせの種類を問わない能力（クラスを引数に取るもの）
     *
     * @var list<string>
     */
    private const CLASS_ABILITIES = ['viewAny', 'create'];

    public function test_notice_model_resolves_to_the_notice_policy(): void
    {
        $this->assertInstanceOf(
            NoticePolicy::class,
            Gate::getPolicyFor(Notice::class),
            'Notice の認可は NoticePolicy が担当する'
        );
    }

    public function test_admin_is_allowed_every_notice_ability(): void
    {
        $admin = User::factory()->admin()->create();
        $notice = Notice::factory()->create();
        $gate = Gate::forUser($admin);

        foreach (self::CLASS_ABILITIES as $ability) {
            $this->assertTrue($gate->allows($ability, Notice::class), "管理者は {$ability} できる");
        }

        foreach (self::NOTICE_ABILITIES as $ability) {
            $this->assertTrue($gate->allows($ability, $notice), "管理者は {$ability} できる");
        }
    }

    public function test_non_admin_is_denied_every_notice_ability(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();
        $gate = Gate::forUser($user);

        foreach (self::CLASS_ABILITIES as $ability) {
            $this->assertTrue($gate->denies($ability, Notice::class), "一般ユーザーは {$ability} できない");
        }

        foreach (self::NOTICE_ABILITIES as $ability) {
            $this->assertTrue($gate->denies($ability, $notice), "一般ユーザーは {$ability} できない");
        }
    }

    public function test_guest_is_denied_every_notice_ability(): void
    {
        $notice = Notice::factory()->create();
        $gate = Gate::forUser(null);

        foreach (self::CLASS_ABILITIES as $ability) {
            $this->assertTrue($gate->denies($ability, Notice::class), "未ログインでは {$ability} できない");
        }

        foreach (self::NOTICE_ABILITIES as $ability) {
            $this->assertTrue($gate->denies($ability, $notice), "未ログインでは {$ability} できない");
        }
    }

    public function test_the_author_of_a_notice_still_cannot_edit_it_without_being_an_admin(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->for($user)->create();
        $gate = Gate::forUser($user);

        $this->assertFalse($gate->allows('update', $notice), '作成者でも管理者でなければ編集できない');
        $this->assertFalse($gate->allows('delete', $notice), '作成者でも管理者でなければ削除できない');
    }

    public function test_an_admin_can_edit_a_notice_created_by_someone_else(): void
    {
        $author = User::factory()->admin()->create();
        $another = User::factory()->admin()->create();
        $notice = Notice::factory()->for($author)->create();
        $gate = Gate::forUser($another);

        $this->assertTrue($gate->allows('update', $notice), '管理者は他人が作ったお知らせも編集できる');
        $this->assertTrue($gate->allows('delete', $notice), '管理者は他人が作ったお知らせも削除できる');
    }

    public function test_gate_authorize_throws_an_authorization_exception_for_a_non_admin(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();

        try {
            Gate::forUser($user)->authorize('update', $notice);
            $this->fail('一般ユーザーの編集は AuthorizationException になるはず');
        } catch (AuthorizationException $e) {
            $this->assertTrue($e->toResponse()->denied(), '拒否として返る');
            // 状態コードを自前で付けていないので、HTTP では Laravel 既定の 403 になる
            $this->assertFalse($e->hasStatus(), '404 などに読み替えていない');
        }
    }

    public function test_policy_does_not_look_at_the_notice_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $notices = [
            '下書き' => Notice::factory()->draft()->create(),
            '公開' => Notice::factory()->create(),
            '予約公開' => Notice::factory()->scheduled()->create(),
        ];

        foreach ($notices as $label => $notice) {
            $this->assertTrue(Gate::forUser($admin)->allows('update', $notice), "管理者は{$label}のお知らせを編集できる");
            $this->assertFalse(Gate::forUser($user)->allows('update', $notice), "一般ユーザーは{$label}のお知らせを編集できない");
        }
    }
}
