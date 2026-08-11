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
        $admin = User::factory()->admin()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => '一般ユーザー',
            'email' => 'user@example.com',
        ]);

        Notice::factory()->for($admin)->create([
            'title' => '管理画面の使い方',
            'body' => 'ログイン後、お知らせの作成・編集・削除ができます。PHPUnit のテストを添えた管理画面サンプルです。',
            'published_at' => now(),
        ]);

        Notice::factory()->for($admin)->scheduled()->create([
            'title' => '予約公開のお知らせ',
            'body' => '公開日時になると自動的に公開扱いになります。',
        ]);

        Notice::factory()->for($admin)->draft()->create([
            'title' => '下書きのお知らせ',
            'body' => 'まだ公開していないお知らせです。',
        ]);

        // ゴミ箱の確認用
        Notice::factory()->for($admin)->create([
            'title' => '削除したお知らせ',
            'body' => 'ゴミ箱から復元するか、完全に削除できます。',
        ])->delete();
    }
}
