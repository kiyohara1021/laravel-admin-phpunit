<?php

namespace Tests\Unit;

use App\Http\Requests\LoginRequest;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    /**
     * 指定したメールアドレスと IP アドレスでリクエストを組み立てる。
     */
    private function loginRequest(string $email, string $ip = '10.0.0.1'): LoginRequest
    {
        return LoginRequest::create(
            '/login',
            'POST',
            ['email' => $email, 'password' => 'password'],
            [],
            [],
            ['REMOTE_ADDR' => $ip],
        );
    }

    public function test_throttle_key_combines_lowercased_email_and_ip(): void
    {
        $key = $this->loginRequest('Admin@Example.com')->throttleKey();

        $this->assertSame('admin@example.com|10.0.0.1', $key, 'キーは小文字化したメールアドレスと IP アドレスの組み合わせ');
    }

    public function test_throttle_key_transliterates_the_email(): void
    {
        $key = $this->loginRequest('ADMIN@ÉXAMPLE.com')->throttleKey();

        $this->assertSame('admin@example.com|10.0.0.1', $key, '非 ASCII はトランスリテレーションされる');
        $this->assertMatchesRegularExpression('/^[\x20-\x7e]+$/', $key, 'キーに生の非 ASCII は残らない');
    }

    public function test_throttle_key_differs_per_ip_address(): void
    {
        $first = $this->loginRequest('admin@example.com', '10.0.0.1')->throttleKey();
        $second = $this->loginRequest('admin@example.com', '10.0.0.2')->throttleKey();

        $this->assertNotSame($first, $second, '同じメールアドレスでも IP アドレスが違えばキーは別');
    }
}
