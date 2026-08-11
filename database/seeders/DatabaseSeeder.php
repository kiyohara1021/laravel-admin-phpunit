<?php

namespace Database\Seeders;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => '一般ユーザー',
            'email' => 'user@example.com',
        ]);

        Notice::factory()->create([
            'title' => '管理画面の使い方',
            'body' => 'ログイン後、お知らせの作成・編集・削除ができます。PHPUnit の練習用サンプルです。',
            'published_at' => now(),
        ]);

        Notice::factory()->draft()->create([
            'title' => '下書きのお知らせ',
            'body' => 'まだ公開していないお知らせです。',
        ]);
    }
}
