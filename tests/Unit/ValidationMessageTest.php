<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationMessageTest extends TestCase
{
    public function test_locale_is_japanese_and_falls_back_to_english(): void
    {
        $this->assertSame('ja', app()->getLocale());
        $this->assertSame('en', app()->getFallbackLocale());
    }

    public function test_rules_without_a_japanese_message_fall_back_to_english_instead_of_the_key(): void
    {
        // lang/ja/validation.php に無いルール。fallback_locale が ja だと翻訳キーがそのまま出る
        $message = Validator::make(['name' => 'ab'], ['name' => 'min:3'], [], ['name' => '名前'])
            ->errors()
            ->first('name');

        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringContainsString('名前', $message);
    }
}
