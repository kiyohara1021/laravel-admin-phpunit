# Laravel 管理画面 PHPUnit 練習

Laravel 13 の管理画面サンプルです。ログイン、ダッシュボード、お知らせの CRUD を PHPUnit で試せます。

## できること

- 管理者ログイン / ログアウト
- ダッシュボード（件数の表示）
- お知らせの作成・編集・削除（公開 / 下書き）

## 起動

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

ブラウザで http://localhost:8000 を開きます。

| ロール | メール | パスワード |
| --- | --- | --- |
| 管理者 | admin@example.com | password |
| 一般ユーザー（403 になる） | user@example.com | password |

## PHPUnit

```bash
php artisan test
```

個別実行の例:

```bash
php artisan test --filter=AuthenticationTest
php artisan test tests/Feature/NoticeManagementTest.php
php artisan test --filter=test_admin_can_create_a_published_notice
```

テストは SQLite のインメモリ DB（`phpunit.xml`）を使い、`RefreshDatabase` でマイグレーションし直します。

| ファイル | 内容 |
| --- | --- |
| `tests/Feature/AuthenticationTest.php` | ログイン・ログアウト・未ログイン時のリダイレクト |
| `tests/Feature/DashboardTest.php` | 管理者のみダッシュボードを見られる |
| `tests/Feature/NoticeManagementTest.php` | お知らせ CRUD とバリデーション |
| `tests/Unit/NoticeTest.php` | 公開判定と `published` スコープ |
