# テスト戦略 / Testing strategy

このリポジトリは、HTTP境界を通すFeatureテストと、状態・認可・値変換を局所的に確認するUnitテストを併用します。

## 実行方法

```bash
composer test
composer analyse
composer lint
composer security
```

個別実行の例:

```bash
php artisan test --filter=AuthenticationTest
php artisan test tests/Feature/NoticeManagementTest.php
php artisan test --filter=test_admin_can_create_a_published_notice
```

テストはSQLiteのインメモリDBを使い、`RefreshDatabase`でスキーマを再作成します。`phpunit.xml`の`BCRYPT_ROUNDS=4`により、認証を繰り返すテストだけを安全に高速化します。

## Feature tests

| ファイル | 主な確認内容 |
| --- | --- |
| `AuthenticationTest.php` | ログイン、ログアウト、未認証リダイレクト |
| `LoginThrottleTest.php` | ロック、残り秒数、成功時クリア、キーの単位 |
| `DashboardTest.php` | 管理者だけが見られる状態別集計 |
| `NoticeManagementTest.php` | CRUD、予約公開、検証、作成者記録 |
| `NoticeTrashTest.php` | ゴミ箱、復元、完全削除、権限 |
| `NoticeAuthorizationTest.php` | 各操作がPolicyを通ること |
| `NoticeSearchTest.php` | 検索、状態、件数、ページャ、公開範囲 |
| `PublicNoticeTest.php` | 公開一覧・詳細、404、本文エスケープ |

## Unit tests

| ファイル | 主な確認内容 |
| --- | --- |
| `NoticeTest.php` | 状態判定、各スコープ、論理削除、作成者 |
| `NoticePolicyTest.php` | Policyの6能力と利用者種別 |
| `NoticeScopeTest.php` | LIKE、ワイルドカード、スコープ合成 |
| `NoticeFilterTest.php` | trim、空、不明な状態の正規化 |
| `LoginRequestTest.php` | メール正規化とIPを含む制限キー |
| `UserTest.php` | 管理者定義が`isAdmin()`へ集約されること |

## CI quality gates

| ジョブ | 内容 |
| --- | --- |
| PHPUnit (PHP 8.4 / 8.5) | 185 tests / 606 assertions |
| Lint | Composer整合性とPint |
| Static analysis | Larastan level 7 |
| Dependency review | 新規依存の既知脆弱性 |

`composer check`はPHPUnit、Larastan、Pintをまとめて実行します。依存関係監査はネットワークに依存するため、`composer security`として明示的に分けています。

## English summary

Feature tests protect HTTP-visible behavior and security boundaries. Unit tests isolate state transitions, query composition, authorization rules, and normalization. CI runs the suite on PHP 8.4 and 8.5 and adds static analysis, style, lockfile, and dependency checks.
