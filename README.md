# Laravel 管理画面 × PHPUnit

[![CI](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml/badge.svg)](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml)

Laravel 13 で管理画面を作り、その振る舞いを PHPUnit の Feature / Unit テストで固めるまでを一通り示すサンプルです。ログイン、権限による出し分け、お知らせの CRUD と予約公開を題材にしています。学習・参照用で、本番運用は想定していません。

## できること

- 管理者ログイン / ログアウト
- ダッシュボード（状態ごとの件数の表示）
- お知らせの作成・編集・削除

## お知らせの状態

お知らせは `published_at` の値ひとつで、次の 3 つの状態を表します（`App\Enums\NoticeStatus`）。

| 状態 | `published_at` | 説明 |
| --- | --- | --- |
| 下書き | `null` | まだ公開しない |
| 公開 | 現在時刻以前 | 公開中 |
| 予約公開 | 未来の日時 | その時刻になると自動的に公開になる |

予約公開を選んだときだけ、公開日時（未来）の入力が必須です。すでに公開中のお知らせを「公開」のまま編集しても、最初の公開日時は変わりません。

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
| `tests/Feature/NoticeManagementTest.php` | お知らせ CRUD、予約公開、バリデーション |
| `tests/Unit/NoticeTest.php` | 状態判定と `published` / `scheduled` / `draft` スコープ |

## コードスタイル

```bash
vendor/bin/pint          # 修正する
vendor/bin/pint --test   # 検査だけする
```

## CI

`main` への push と Pull Request で [GitHub Actions](.github/workflows/ci.yml) が動きます。

| ジョブ | 内容 |
| --- | --- |
| PHPUnit | PHP 8.3 / 8.4 の両方で `php artisan test` |
| Pint | `vendor/bin/pint --test` でコードスタイルを検査 |
