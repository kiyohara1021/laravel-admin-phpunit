# Changelog

このプロジェクトの主な変更を記録します。形式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に従い、バージョンは [Semantic Versioning](https://semver.org/lang/ja/) を使用します。

## [Unreleased]

### Added

- Larastan level 7による静的解析とCIジョブ。
- 英語README、設計概要、コントリビューション・セキュリティ・サポート文書。
- Issueフォーム、Pull Requestテンプレート、Dependabot設定。

### Changed

- Eloquentの日時属性と検証済み入力の型境界を明示。
- Composerパッケージの説明・キーワード・サポートURLを改善。

## [1.0.0] - 2026-08-14

### Added

- 管理者認証とログイン試行のレート制限。
- お知らせCRUD、予約公開、論理削除、復元、完全削除、作成者記録。
- 管理画面・公開画面の検索と状態フィルター。
- ミドルウェア・Policy・Gateによる認可。
- PHPUnitのFeature / UnitテストとPHP 8.4・8.5のCI。

[Unreleased]: https://github.com/kiyohara1021/laravel-admin-phpunit/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/kiyohara1021/laravel-admin-phpunit/releases/tag/v1.0.0
