# 状態モデル / Domain model

このサンプルでは、お知らせの公開状態、論理削除、作成者履歴を、重複した状態を持たせずに表現します。

## 公開状態

`published_at`を正本とし、`App\Enums\NoticeStatus`が次の3状態へ解釈します。

| 状態 | 条件 | 意味 |
| --- | --- | --- |
| 下書き / Draft | `published_at IS NULL` | 公開しない |
| 公開 / Published | `published_at <= now()` | 公開中 |
| 予約公開 / Scheduled | `published_at > now()` | 指定時刻から公開 |

状態列と日時列を別々に持つと、「公開なのに日時がない」といった矛盾が発生します。この実装では日時だけを正本にし、状態判定をモデルとスコープへ集約します。

予約公開を選んだ場合だけ未来の公開日時を必須にします。公開済みのお知らせを「公開」のまま編集しても、最初の公開日時は維持します。

## 論理削除

`Notice`は`SoftDeletes`を使います。通常の削除では`deleted_at`だけが設定され、ゴミ箱から復元または完全削除できます。

Eloquentのグローバルスコープにより、削除済みレコードは通常一覧、ダッシュボード集計、`draft()`、`published()`、`scheduled()`へ混ざりません。ゴミ箱の操作では`onlyTrashed()`を明示します。

## 作成者履歴

作成時の管理者を`user_id`へ保存し、編集者が変わっても作成者は変更しません。ユーザー削除時は外部キーを`null`へ戻し、履歴画面では「—」として安全に表示します。

## 公開側の取得規則

公開一覧と詳細は、必ず`Notice::published()`からクエリを始めます。詳細では暗黙のRoute Model Bindingを使わず、次の形で取得します。

```php
Notice::query()->published()->findOrFail($id);
```

下書き、予約公開、削除済み、存在しないIDはすべて同じ404となり、URLを直接指定しても非公開レコードの存在を判別できません。

## Production boundary

This model is intentionally small for teaching. Production systems may additionally need editorial workflows, immutable audit logs, timezone policy, and concurrent-edit protection.
