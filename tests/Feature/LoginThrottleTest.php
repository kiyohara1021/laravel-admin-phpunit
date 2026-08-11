<?php

namespace Tests\Feature;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const LOCKOUT_MESSAGE_HEAD = 'ログイン試行の回数が上限に達しました。';

    private const CREDENTIALS_MESSAGE = 'メールアドレスまたはパスワードが正しくありません。';

    private function postLogin(string $email, string $password): TestResponse
    {
        return $this->from(route('login'))->post(route('login.store'), [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * 指定した回数だけ失敗させる。
     */
    private function failLogin(string $email, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->postLogin($email, 'wrong-password');
        }
    }

    /**
     * リダイレクト先セッションに入ったメールアドレス欄のエラー文言を取り出す。
     */
    private function emailError(TestResponse $response): string
    {
        $response->assertSessionHasErrors('email');

        return session('errors')->first('email');
    }

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);

        $response = $this->postLogin($user->email, 'wrong-password')
            ->assertRedirect(route('login'));

        $this->assertStringStartsWith(self::LOCKOUT_MESSAGE_HEAD, $this->emailError($response), '上限に達した後はロックのメッセージになる');
        $this->assertGuest();
    }

    public function test_the_limit_is_five_attempts_within_sixty_seconds(): void
    {
        // 他のテストは定数を参照するので、値そのものはここで固定する
        $this->assertSame(5, LoginRequest::MAX_ATTEMPTS, '失敗を許すのは 5 回まで');
        $this->assertSame(60, LoginRequest::DECAY_SECONDS, 'ロックが解けるまでは 60 秒');
    }

    public function test_literal_five_failures_are_enough_to_lock_the_account(): void
    {
        $user = User::factory()->admin()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postLogin($user->email, 'wrong-password');
        }

        $response = $this->postLogin($user->email, 'password');

        $this->assertStringStartsWith(self::LOCKOUT_MESSAGE_HEAD, $this->emailError($response), '5 回失敗した時点でロックされる');
        $this->assertGuest();
    }

    public function test_the_lock_is_released_after_the_decay_time_has_passed(): void
    {
        $user = User::factory()->admin()->create();

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);

        $response = $this->postLogin($user->email, 'password');
        $this->assertStringStartsWith(self::LOCKOUT_MESSAGE_HEAD, $this->emailError($response), 'まずはロックされている');
        $this->assertGuest();

        $this->travel(LoginRequest::DECAY_SECONDS + 1)->seconds();

        $this->postLogin($user->email, 'password')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_lock_is_still_in_effect_just_before_the_decay_time(): void
    {
        $user = User::factory()->admin()->create();

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);

        $this->travel(LoginRequest::DECAY_SECONDS - 1)->seconds();

        $response = $this->postLogin($user->email, 'password');

        $this->assertStringStartsWith(self::LOCKOUT_MESSAGE_HEAD, $this->emailError($response), '待ち時間が過ぎるまではロックされたまま');
        $this->assertGuest();
    }

    public function test_the_remaining_seconds_never_exceed_the_decay_time(): void
    {
        $user = User::factory()->admin()->create();

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);
        $response = $this->postLogin($user->email, 'wrong-password');

        preg_match('/(\d+) 秒後/', $this->emailError($response), $matches);

        $this->assertNotEmpty($matches, '残り秒数がメッセージに含まれること');
        $this->assertGreaterThan(0, (int) $matches[1], '残り秒数は 0 より大きいこと');
        $this->assertLessThanOrEqual(LoginRequest::DECAY_SECONDS, (int) $matches[1], '残り秒数は待ち時間を超えないこと');
    }

    public function test_attempts_before_the_limit_show_the_credentials_error(): void
    {
        $user = User::factory()->admin()->create();

        for ($i = 1; $i <= LoginRequest::MAX_ATTEMPTS; $i++) {
            $response = $this->postLogin($user->email, 'wrong-password');

            $this->assertSame(self::CREDENTIALS_MESSAGE, $this->emailError($response), "{$i} 回目まではロックされない");
        }
    }

    public function test_lockout_message_tells_how_many_seconds_to_wait(): void
    {
        $user = User::factory()->admin()->create();

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);
        $response = $this->postLogin($user->email, 'wrong-password');

        $this->assertMatchesRegularExpression('/\d+ 秒後/', $this->emailError($response), 'あと何秒待てばよいかを伝える');
    }

    public function test_lockout_event_is_dispatched(): void
    {
        $user = User::factory()->admin()->create();

        Event::fake([Lockout::class]);

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);
        Event::assertNotDispatched(Lockout::class);

        $this->postLogin($user->email, 'wrong-password');
        Event::assertDispatched(Lockout::class, 1);
    }

    public function test_correct_password_is_rejected_while_locked_out(): void
    {
        $user = User::factory()->admin()->create();

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);

        $response = $this->postLogin($user->email, 'password');

        $this->assertStringStartsWith(self::LOCKOUT_MESSAGE_HEAD, $this->emailError($response), 'ロック中は正しいパスワードでも弾かれる');
        $this->assertGuest();
    }

    public function test_successful_login_clears_the_throttle_counter(): void
    {
        $user = User::factory()->admin()->create();

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS - 1);

        $this->postLogin($user->email, 'password')
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'));
        $this->assertGuest();

        // 成功でカウンタが消えているので、また上限手前まで失敗できる
        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS - 1);

        $this->postLogin($user->email, 'password')
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_throttle_is_scoped_per_email_address(): void
    {
        $locked = User::factory()->admin()->create(['email' => 'locked@example.com']);
        $other = User::factory()->admin()->create(['email' => 'other@example.com']);

        $this->failLogin($locked->email, LoginRequest::MAX_ATTEMPTS);

        // 別のメールアドレスは制限を受けない
        $this->postLogin($other->email, 'password')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($other);
    }

    public function test_throttle_ignores_email_letter_case(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->failLogin('admin@example.com', LoginRequest::MAX_ATTEMPTS);

        $response = $this->postLogin('Admin@Example.COM', 'password');

        $this->assertStringStartsWith(self::LOCKOUT_MESSAGE_HEAD, $this->emailError($response), '大文字小文字を変えても制限は回避できない');
        $this->assertGuest();
    }

    public function test_lockout_message_is_shown_on_the_login_screen(): void
    {
        $user = User::factory()->admin()->create();

        $this->failLogin($user->email, LoginRequest::MAX_ATTEMPTS);

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertOk()
            ->assertSee(self::LOCKOUT_MESSAGE_HEAD);
    }
}
