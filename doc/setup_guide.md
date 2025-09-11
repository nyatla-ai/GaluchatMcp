# セットアップガイド

本プロジェクトの最小構成をセットアップする手順を示します。PHP 8.2 以上と Composer がインストールされている前提です。

## 1. 依存パッケージのインストール

```bash
composer install --no-progress
```

## 2. 設定ファイルの編集

`config/config.dev.php` を編集して Galuchat API のベースURLやタイムアウトを設定します。

| 設定キー | 内容 | 例 |
| --- | --- | --- |
| `galuchat.base_url` | Galuchat API のベースURL | `https://galuchat.example.com` |
| `galuchat.timeout_ms` | HTTP クライアントのタイムアウト (ms) | `10000` |

## 3. サーバーの起動

PHP のビルトインサーバーを利用して起動できます。

```bash
php -S localhost:8080 -t app/public
```

## 4. エンドポイント

- `GET /mcp/manifest`
- `POST ../tools/resolve_points`
- `POST ../tools/summarize_stays`

マニフェスト内の `endpoint` は、アクセスしたマニフェストの URL を基準に算出された絶対 URL として返されます。

詳細は [README.md](../README.md) を参照してください。

## 5. テストの実行

```bash
./vendor/bin/phpunit tests
```

