# セットアップガイド

本プロジェクトの最小構成をセットアップする手順を示します。PHP 8.2 以上と Composer がインストールされている前提です。

## 1. 依存パッケージのインストール

```bash
composer install --no-progress
```

## 2. 環境変数の設定

`.env.example` を `.env` にコピーし、Galuchat API のベースURLなどを設定します。

```bash
cp app/.env.example app/.env
# 必要に応じて値を編集
```

| 変数名 | 内容 | 例 |
| --- | --- | --- |
| `GALUCHAT_BASE_URL` | Galuchat API のベースURL | `https://galuchat.example.com` |
| `TIMEOUT_MS` | HTTP クライアントのタイムアウト (ms) | `3000` |

## 3. サーバーの起動

PHP のビルトインサーバーを利用して起動できます。

```bash
php -S localhost:8080 -t app/public
```

## 4. エンドポイント

- `GET /mcp/manifest`
- `POST ../tools/resolve_points`
- `POST ../tools/summarize_stays`

詳細は [README.md](../README.md) を参照してください。

## 5. テストの実行

```bash
./vendor/bin/phpunit tests
```

