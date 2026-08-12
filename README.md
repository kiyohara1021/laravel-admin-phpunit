# Laravel 管理画面 × PHPUnit

[![CI](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml/badge.svg)](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Laravel 13 で管理画面を作り、その振る舞いを PHPUnit の Feature / Unit テストで固めるまでを一通り示すサンプルです。ログイン、権限による出し分け、お知らせの CRUD と予約公開を題材にしています。学習・参照用で、本番運用は想定していません。

## できること

- 管理者ログイン / ログアウト（ログイン試行のレート制限つき）
- ダッシュボード（状態ごとの件数の表示）
- お知らせの作成・編集・削除（削除は論理削除）
- 作成者（作成したときのログイン中の管理者）の記録と一覧表示
- ゴミ箱（削除済み一覧・復元・完全削除）
- 一覧の絞り込み（キーワード検索と状態フィルタ）
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

## 一覧の絞り込み

一覧の 3 画面にキーワード検索と状態フィルタを付けています。

| 画面 | URL | `q`（キーワード） | `status`（状態） |
| --- | --- | --- | --- |
| 管理画面のお知らせ一覧 | `/admin/notices` | あり | あり |
| ゴミ箱 | `/admin/notices/trashed` | あり | なし（「削除済み」の一つしか状態がないため） |
| 公開側の一覧 | `/notices` | あり | なし（公開中だけが対象なので） |

- `q` はタイトルと本文の部分一致です。前後の空白は無視し、空文字や空白だけのときは絞り込みません。
- `status` には `NoticeStatus` の値（`draft` / `published` / `scheduled`）を使います。未指定なら全件です。
- 存在しない `status`（例: `?status=archived`）は**エラーにせず無視**して全件を出します。一覧の絞り込みは URL に載った表示状態であって命令ではないので、古いブックマークやタイポで管理画面がエラーになる方が害が大きい、という判断です。無視されたことは状態のセレクトが「すべて」に戻ることで見て分かります。
- 絞り込み中は「該当 N 件」を出し、0 件のときは「検索条件に一致するお知らせはありません。」を出します。絞り込みをしていないときの文言は従来どおりです。
- ページャのリンクは `withQueryString()` で絞り込み条件を保つので、2 ページ目に行っても条件が消えません。

クエリの組み立ては 3 つのコントローラにコピーせず、`Notice` のスコープに寄せています。

| スコープ | 役割 |
| --- | --- |
| `Notice::search(?string $keyword)` | タイトル・本文の部分一致。空なら素通り |
| `Notice::ofStatus(?NoticeStatus $status)` | `draft()` / `published()` / `scheduled()` に委譲。`null` なら素通り |

`search()` は OR を必ず入れ子の `where` で包みます。包まないと `published()` などの前の条件の外に OR が漏れ、公開側の検索に下書きが混ざりかねないためです。生成される SQL はこうなります。

```sql
select * from "notices"
where "published_at" is not null and "published_at" <= ?
  and (title like ? escape '!' or body like ? escape '!')
  and "notices"."deleted_at" is null
order by "published_at" desc, "id" desc
```

検索語の `%` と `_` は自前でエスケープし、`ESCAPE` 句を明示しています。SQLite は `ESCAPE` 句がないと既定のエスケープ文字を持たないため、句を省くとエスケープしたつもりの記号がリテラル扱いされて何にも当たらなくなります。`whereLike()` を使わないのも同じ理由で、`ESCAPE` 句を付けてくれないからです。これがないと「100%」で検索したときに「100」を含む行が全部返ってしまいます。

エスケープ記号にはバックスラッシュではなく `!` を使っています。SQL 中に `escape '\'` という並びができると、`toRawSql()` / `dumpRawSql()` の文字列リテラル判定が閉じ引用符を読み飛ばし、それ以降のバインディングが `?` のまま残るためです（実行される SQL は `toSql()` とバインディングなので結果は正しく、壊れるのはデバッグ出力だけですが、調査のたびに誤読します）。記号自身も `!!` に二重化するので、`!` を含む語も普通に検索できます。

クエリ文字列の読み取りは値オブジェクト `App\Support\NoticeFilter` にまとめています。GET の絞り込みは「弾くべき入力」ではなく表示状態で、不正値は設計上すべて無視するため、検証を担う FormRequest ではなく値オブジェクトにしました（POST / PUT の本文は従来どおり FormRequest です）。

## 認可

認可は二段構えで、置き場所を役割で分けています。

| 層 | 担当 | 単位 | 何を見るか |
| --- | --- | --- | --- |
| `App\Http\Middleware\EnsureUserIsAdmin`（`admin` エイリアス） | 管理画面という区画に入れるか | ルートグループ | 管理者でなければ 403 |
| `App\Policies\NoticePolicy` | そのお知らせに何をしてよいか | 資源（`Notice`） | 同じく管理者かどうか。作成者かどうかは見ない |

ミドルウェアはリクエストの入口で、ダッシュボードのようにお知らせと無関係な画面も含めて `/admin/...` をまとめて守ります。Policy は `Notice` 1 件に対する操作の可否を決め、単体でテストできます。

役割は分けていますが、「管理者とは何か」の定義は `User::isAdmin()` の 1 か所だけです。ミドルウェアと Policy の両方がこれを呼ぶので、管理者の条件を変えるときに片方だけ直して食い違う、ということが起きません。

Policy が持つ能力は `viewAny` / `create` / `update` / `delete` / `restore` / `forceDelete` の 6 つです。`Notice` モデルに `#[UsePolicy(NoticePolicy::class)]` を付けて紐付け（`Gate::getPolicyFor()` が属性から解決します）、コントローラの各アクションの先頭で `Gate::authorize()` を呼びます。

```php
public function destroy(Notice $notice): RedirectResponse
{
    Gate::authorize('delete', $notice);
    // ...
}
```

FormRequest（`StoreNoticeRequest` / `UpdateNoticeRequest`）の `authorize()` は `true` を返すだけで、検証だけを担当します。管理者かどうかの判定を FormRequest にも書くと同じ規則が二重になるためです。非管理者はミドルウェアがバリデーションより前に 403 を返すので、この一本化で穴は空きません。

管理者は自分が作っていないお知らせも編集・削除できます。逆に、一般ユーザーは自分が作ったお知らせでも操作できません（403）。公開側の画面には認可を足していません（ログイン不要）。

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

パスワードのハッシュは `phpunit.xml` の `BCRYPT_ROUNDS=4` で軽くしています。この値を読ませるために `config/hashing.php` を置いています。このファイルを消すと設定が既定の 12 ラウンドに戻り、ログインを繰り返すテスト（レート制限まわり）が極端に遅くなります。

| ファイル | 内容 |
| --- | --- |
| `tests/Feature/AuthenticationTest.php` | ログイン・ログアウト・未ログイン時のリダイレクト |
| `tests/Feature/LoginThrottleTest.php` | ログイン試行のレート制限（ロック、残り秒数、成功時のクリア、キーの単位） |
| `tests/Feature/DashboardTest.php` | 管理者のみダッシュボードを見られる |
| `tests/Feature/NoticeManagementTest.php` | お知らせ CRUD、予約公開、バリデーション、作成者の記録 |
| `tests/Feature/NoticeTrashTest.php` | ゴミ箱の一覧・復元・完全削除と権限 |
| `tests/Feature/NoticeAuthorizationTest.php` | 各アクションが `NoticePolicy` を通ること、公開側は通らないこと |
| `tests/Feature/NoticeSearchTest.php` | 3 画面の絞り込み（キーワード・状態・件数・ページャの条件保持・検索ボックスのエスケープ・公開側に漏れないこと） |
| `tests/Feature/PublicNoticeTest.php` | 公開側の一覧・詳細、公開中以外が 404 になること、本文のエスケープ |
| `tests/Unit/NoticeTest.php` | 状態判定、`published` / `scheduled` / `draft` スコープ、論理削除と作成者 |
| `tests/Unit/NoticePolicyTest.php` | `NoticePolicy` の 6 能力（管理者・一般ユーザー・未ログイン・作成者の扱い） |
| `tests/Unit/NoticeScopeTest.php` | `search` / `ofStatus` スコープ（部分一致、`%` `_` `\` `!` のエスケープ、スコープ外に漏れないこと） |
| `tests/Unit/NoticeFilterTest.php` | `NoticeFilter` のクエリ文字列の読み取り（trim、空扱い、不正な状態の無視） |
| `tests/Unit/LoginRequestTest.php` | レート制限のキー（小文字化・トランスリテレーション・IP ごと） |
| `tests/Unit/UserTest.php` | `User::isAdmin()`（管理者の定義が 1 か所であること） |

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

## ライセンス

このリポジトリのコードは MIT ライセンスです。全文は [LICENSE](LICENSE) にあります（`composer.json` の `license` も `MIT` で揃えています）。

複製・改変・再配布・商用利用のいずれも自由ですが、複製物または重要な部分には上記の著作権表示とライセンス本文を含めてください。無保証であり、作者は本ソフトウェアの利用によって生じた損害について責任を負いません。

### 第三者のソフトウェア

`composer install` / `npm install` で入る依存パッケージ（Laravel フレームワーク、PHPUnit、Pint、Vite、Tailwind CSS など）は、それぞれの配布元のライセンスに従います。このリポジトリの MIT ライセンスが及ぶのは、あくまでここにコミットされているコードだけです。依存パッケージのライセンスは、インストール後に次のコマンドで一覧できます。

```bash
composer licenses
```

### 学習用サンプルであることについて

このリポジトリは学習・参照用のサンプルで、本番運用は想定していません。`database/seeders` に入っているアカウント（`admin@example.com` / `password` など）や `.env.example` の設定は、そのまま公開環境で使わないでください。ライセンス上は自由に利用できますが、安全性についての保証はありません。
