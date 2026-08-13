# 検索とフィルタリング / Search and filtering

管理一覧、ゴミ箱、公開一覧は同じ検索規則を共有しつつ、それぞれの公開範囲を維持します。

## 対応画面

| 画面 | URL | キーワード | 状態 |
| --- | --- | --- | --- |
| 管理一覧 | `/admin/notices` | `q` | `status` |
| ゴミ箱 | `/admin/notices/trashed` | `q` | なし |
| 公開一覧 | `/notices` | `q` | 公開中のみ |

- 前後の空白を除去し、空文字は検索条件にしません。
- 不明な`status`は422にせず無視します。GETの絞り込みは命令ではなく表示状態だからです。
- ページャは`withQueryString()`で検索条件を維持します。
- 0件と未検索状態を異なる文言で表示します。

## クエリスコープ

コントローラ間で条件をコピーせず、`Notice`のスコープへ集約します。

| スコープ | 役割 |
| --- | --- |
| `search(?string $keyword)` | タイトル・本文の部分一致。空なら素通り |
| `ofStatus(?NoticeStatus $status)` | 状態スコープへ委譲。`null`なら素通り |

OR検索は必ず入れ子の`where`で包みます。包まない場合、ORが前段の`published()`条件から漏れ、公開検索に下書きが混ざる可能性があります。

```sql
select * from "notices"
where "published_at" is not null and "published_at" <= ?
  and (title like ? escape '!' or body like ? escape '!')
  and "notices"."deleted_at" is null
order by "published_at" desc, "id" desc
```

## SQLワイルドカード

利用者が入力した`%`と`_`をそのままLIKEへ渡すと、任意文字として評価されます。このサンプルは`!`をエスケープ文字として、次をリテラル検索へ変換します。

| 入力 | LIKEパターン内 |
| --- | --- |
| `%` | `!%` |
| `_` | `!_` |
| `!` | `!!` |

SQLiteでは`ESCAPE`句を明示しないと、エスケープ済みの記号が期待通りに動きません。そのため`whereLike()`ではなく、バインディング付きのLIKEと`ESCAPE '!'`を使います。

バックスラッシュを避ける理由は、Laravelの`toRawSql()` / `dumpRawSql()`がデバッグ用SQLを組み立てる際の引用符判定を読みやすく保つためです。実行クエリだけでなく、調査時の出力も誤読しにくくします。

## 表示状態の値オブジェクト

`App\Support\NoticeFilter`がGETクエリをtrimし、不明な状態を`null`へ正規化します。POST / PUTの本文はFormRequestで検証しますが、表示用GETパラメータは古いブックマークやタイポを壊さない値オブジェクトとして扱います。

## English summary

Search scopes are always composed under the publication scope, wildcard characters are escaped explicitly for SQLite, and malformed GET filters degrade to an unfiltered view rather than an exception.
