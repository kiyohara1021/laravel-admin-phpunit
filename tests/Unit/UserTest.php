<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_admin_is_true_only_for_an_administrator(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->isAdmin(), '管理者は true');
        $this->assertFalse(User::factory()->create()->isAdmin(), '一般ユーザーは false');
    }

    /**
     * 真偽値以外が入っていても true にしない（キャストを外したときに気づけるように）。
     */
    public function test_is_admin_is_strict_about_its_value(): void
    {
        $user = User::factory()->create();
        $user->is_admin = 1;

        $this->assertTrue($user->isAdmin(), 'boolean にキャストされてから判定する');
    }
}
