# セットアップガイド

本プロジェクトの最小構成をセットアップする手順を示します。PHP 8.2 以上と Composer がインストールされている前提です。

## 1. 依存パッケージのインストール

```bash
composer install --no-progress
```

## 2. 設定ファイルの編集

`config/config.dev.php` を編集して Galuchat API の接続先やタイムアウトを設定します。アプリをサブディレクトリに配置する場合は URL 接頭辞 `app.url_prefix` も変更してください。

| 設定キー | 内容 | 例 |
| --- | --- | --- |
| `galuchat.api_url_prefix` | Galuchat API の URL プレフィックス | `https://galuchat.example.com` |
| `galuchat.timeout_ms` | HTTP クライアントのタイムアウト (ms) | `10000` |
| `app.url_prefix` | サーバーの URL 接頭辞 | `/mcp` |

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

