<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('管理画面ログイン');
    }

    public function test_admin_can_authenticate(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->admin()->create();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_an_email_and_a_password(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => '',
                'password' => '',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password']);

        $errors = session('errors');

        $this->assertStringContainsString('メールアドレス', $errors->first('email'), 'メールアドレスの属性名が日本語になること');
        $this->assertStringContainsString('パスワード', $errors->first('password'), 'パスワードの属性名が日本語になること');
        $this->assertGuest();
    }

    public function test_login_rejects_a_malformed_email_address(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'not-an-email',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('メールアドレス', session('errors')->first('email'), 'メールアドレスの属性名が日本語になること');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
