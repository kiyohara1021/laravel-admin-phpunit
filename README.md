# Laravel 管理画面 × PHPUnit

[![CI](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml/badge.svg)](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml)

Laravel 13 で管理画面を作り、その振る舞いを PHPUnit の Feature / Unit テストで固めるまでを一通り示すサンプルです。ログイン、権限による出し分け、お知らせの CRUD と予約公開を題材にしています。学習・参照用で、本番運用は想定していません。

## できること

- 管理者ログイン / ログアウト（ログイン試行のレート制限つき）
- ダッシュボード（状態ごとの件数の表示）
- お知らせの作成・編集・削除（削除は論理削除）
- 作成者（作成したときのログイン中の管理者）の記録と一覧表示
- ゴミ箱（削除済み一覧・復元・完全削除）
- 公開側のお知らせ一覧・詳細（ログイン不要）

## お知らせの状態

お知らせは `published_at` の値ひとつで、次の 3 つの状態を表します（`App\Enums\NoticeStatus`）。

| 状態 | `published_at` | 説明 |
| --- | --- | --- |
| 下書き | `null` | まだ公開しない |
| 公開 | 現在時刻以前 | 公開中 |
| 予約公開 | 未来の日時 | その時刻になると自動的に公開になる |

予約公開を選んだときだけ、公開日時（未来）の入力が必須です。すでに公開中のお知らせを「公開」のまま編集しても、最初の公開日時は変わりません。

## 論理削除と作成者

削除は `SoftDeletes` による論理削除です。削除したお知らせは `deleted_at` が入るだけで行は残り、ゴミ箱（お知らせ一覧の「ゴミ箱」リンク）から復元・完全削除ができます。グローバルスコープが効くため、通常の一覧・ダッシュボードの件数・`published` / `scheduled` / `draft` の各スコープに削除済みは混ざりません。

お知らせには作成者（`user_id`）を持たせています。作成時にログイン中の管理者を記録し、あとから編集しても作成者は変わりません。作成者のユーザーを削除した場合は `user_id` が `null` に戻り、一覧では「—」と表示されます。

## 公開側の画面

ログイン不要で読める画面です。管理画面（`/admin/...`）とは別のコントローラ・レイアウトを使っています。

| URL | ルート名 | 内容 |
| --- | --- | --- |
| `/notices` | `notices.index` | 公開中のお知らせを公開日時の新しい順に 10 件ずつ表示 |
| `/notices/{id}` | `notices.show` | お知らせの詳細 |

見せるのは公開中のものだけです。詳細は `Notice::query()->published()->findOrFail($id)` で引くため、下書き・予約公開・論理削除済み・そもそも存在しない ID がすべて同じ 404 になり、URL を直接叩いても存在を判別できません。本文はプレーンテキストで、`nl2br(e($notice->body))` としてエスケープしてから改行を活かします。作成者名は公開側には表示しません。

なお `/` は従来どおり管理画面のダッシュボードへリダイレクトします。

## ログインのレート制限

総当たり攻撃を避けるため、ログイン試行に `Illuminate\Support\Facades\RateLimiter` で回数制限をかけています。カウントの単位（キー）は「メールアドレス（小文字化・トランスリテレーション）＋ IP アドレス」で、`App\Http\Requests\LoginRequest` の `throttleKey()` が組み立てます。

- 失敗が 5 回（`LoginRequest::MAX_ATTEMPTS`）に達すると、以降の試行は正しいパスワードでも弾かれます。
- ロック中はメールアドレス欄に「あと何秒待てばよいか」を含むエラーが出て、`Illuminate\Auth\Events\Lockout` が発行されます。
- 待ち時間は 60 秒（`LoginRequest::DECAY_SECONDS`）で、ログインに成功するとカウンタは消えます。

判定は `LoginRequest::ensureIsNotRateLimited()` にまとめてあり、`LoginController::store()` は「ロック確認 → 失敗なら `RateLimiter::hit` → 成功なら `RateLimiter::clear`」の 3 手順だけです。キーの組み立ては DB に依存しないので Unit テストから、ロックの挙動は Feature テストから HTTP 経由で確認しています。

## 起動

PHP 8.4 以上が必要です（`composer.lock` が固定している Symfony 8.1 系の要求）。

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
| `tests/Feature/LoginThrottleTest.php` | ログイン試行のレート制限（ロック、残り秒数、成功時のクリア、キーの単位） |
| `tests/Feature/DashboardTest.php` | 管理者のみダッシュボードを見られる |
| `tests/Feature/NoticeManagementTest.php` | お知らせ CRUD、予約公開、バリデーション、作成者の記録 |
| `tests/Feature/NoticeTrashTest.php` | ゴミ箱の一覧・復元・完全削除と権限 |
| `tests/Feature/PublicNoticeTest.php` | 公開側の一覧・詳細、公開中以外が 404 になること、本文のエスケープ |
| `tests/Unit/NoticeTest.php` | 状態判定、`published` / `scheduled` / `draft` スコープ、論理削除と作成者 |
| `tests/Unit/LoginRequestTest.php` | レート制限のキー（小文字化・トランスリテレーション・IP ごと） |

## コードスタイル

```bash
vendor/bin/pint          # 修正する
vendor/bin/pint --test   # 検査だけする
```

## CI

`main` への push と Pull Request で [GitHub Actions](.github/workflows/ci.yml) が動きます。

| ジョブ | 内容 |
| --- | --- |
| PHPUnit | PHP 8.4 / 8.5 の両方で `php artisan test` |
| Pint | `vendor/bin/pint --test` でコードスタイルを検査 |
