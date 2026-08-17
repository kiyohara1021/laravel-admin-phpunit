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

## 実行時の約束

`phpunit.xml`で、通ってしまう曖昧なテストを落とすよう設定しています。

| 設定 | 何を防ぐか |
| --- | --- |
| `executionOrder="random"` | 前のテストが残した状態に寄りかかったテスト |
| `beStrictAboutTestsThatDoNotTestAnything` | 何も検証していないのに緑になるテスト |
| `beStrictAboutOutputDuringTests` | 消し忘れた`dump()`や`echo` |
| `failOnRisky` / `failOnWarning` / `failOnNotice` | 警告として見過ごされる不備 |
| `failOnDeprecation` / `failOnPhpunitDeprecation` | PHPやLaravelの更新で消える機能への依存 |

順序をランダムにしているため、`--filter`で1件だけ通ることと、全体が通ることは別物です。壊れたときは失敗メッセージに出る`--order-by=defects`や乱数シードで再現できます。

## テスト用のヘルパ

`tests/TestCase.php`に、ビューやセッションから値を取り出す口をまとめています。`viewData()`や`session('errors')`は`mixed`を返すので、各テストで散らかると型が追えなくなります。取り出しと同時に「期待した型で入っていること」を検証し、以降を型付きで書けるようにしています。

| ヘルパ | 返すもの |
| --- | --- |
| `noticesOf($response)` | ビューに渡った`LengthAwarePaginator<int, Notice>` |
| `nextPageUrlOf($response)` | 次ページへのリンク。無ければ失敗させる |
| `firstError($key)` | 指定した項目の先頭のバリデーションメッセージ |

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
| PHPUnit (PHP 8.4 / 8.5) | 185 tests / 674 assertions |
| Coverage | PCOVで計測し、結果を実行サマリに出す |
| Lint | Composer整合性とPint |
| Static analysis | Larastan level 10（`app`から`tests`まで） |
| Dependency audit | `composer.lock`の既知脆弱性 |
| Dependency review | 新規依存の既知脆弱性 |

`composer check`はPHPUnit、Larastan、Pintをまとめて実行します。依存関係監査はネットワークに依存するため、`composer security`として明示的に分けています。

依存の脆弱性はこちらがコードを触らなくても後から公表されるため、Dependency auditは毎週月曜にも動かします。

## English summary

Feature tests protect HTTP-visible behavior and security boundaries. Unit tests isolate state transitions, query composition, authorization rules, and normalization. Tests run in a randomized order, and risky tests, output, warnings, and deprecations all fail the build. CI runs the suite on PHP 8.4 and 8.5 and adds coverage, static analysis at Larastan level 10 over both application and test code, style, lockfile, and dependency checks.
