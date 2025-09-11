# README（日本語版）

最小限のMCPサーバーで、座標から行政区画情報を取得します

## エンドポイント
マニフェスト: https://nyatla.jp/galuchat-mcp/manifest.json

ツールの `endpoint` フィールドには、このマニフェストを取得した URL を基準に算出された絶対 URL が含まれます。


## 必要条件

- PHP 8.2 以上
- Composer

## インストール

```bash
composer install
```

接続設定は `config/config.dev.php` に記述されています。Galuchat API への接続先 URL プレフィックスは `galuchat.api_url_prefix` で指定します。サブディレクトリで動かす場合はアプリケーションの URL 接頭辞 `app.url_prefix` を設定ファイルで変更してください。

### `app.url_prefix` の設定例

#### レンタルサーバーのサブディレクトリに配置する場合

`app/public` をレンタルサーバーの `public_html/example` など任意のサブディレクトリにアップロードし、`https://example.com/example/mcp` で公開する場合は、設定ファイルに次のように追記します。

```php
'app' => [
    'url_prefix' => '/example/mcp',
],
```

この設定でマニフェストは `https://example.com/example/mcp/manifest.json` から取得できます。

#### リポジトリ全体をサブディレクトリに配置する場合

リポジトリを `https://example.com/galuchat-mcp` などのサブディレクトリにそのまま設置する場合は、
同梱の `index.php` と `.htaccess` が自動的に `app/public/index.php` をフロントコントローラとして読み込みます。
設定ファイルの `app.url_prefix` を設置したサブディレクトリに合わせてください（例: `/galuchat-mcp`）。

開発時に PHP のビルトインサーバーを利用する場合は次のコマンドで起動できます。

```bash
php -S localhost:8080 -t . index.php
```

#### PHP のビルトインサーバーで動作させる場合

開発用に `php -S` を利用し、ベースパスを `/` にしたいときは次のように設定します。

```php
'app' => [
    'url_prefix' => '/',
],
```

サーバーは次のコマンドで起動します。

```bash
php -S localhost:8080 -t app/public
```

上記では `http://localhost:8080/manifest.json` でマニフェストを取得できます。`app.url_prefix` を `/mcp` のままにした場合は `http://localhost:8080/mcp/manifest.json` となります。

## 起動方法

PHP のビルトインサーバーで起動する場合:
```bash
php -S localhost:8080 -t app/public
```

## エンドポイント

これらのパスは設定ファイルの `app.url_prefix`（デフォルト `/mcp`）をベースパスとして公開されます。

### `GET /manifest.json`

利用可能なツールのマニフェストを返します。各ツールの `endpoint` フィールドは、このマニフェストを取得した URL を基準に算出された絶対 URL として返されます。

**curl ワンライナー**
```bash
curl http://localhost:8080/mcp/manifest.json
```

### `POST /tools/resolve_points`

位置情報の配列を行政区コードと住所に解決します。
各入力ポイントが順番に `results` に対応し、`ref`（任意）と解決された `code`・`address` を返します。

**curl ワンライナー**
```bash
curl -X POST http://localhost:8080/mcp/tools/resolve_points \
  -H "Content-Type: application/json" \
  -d '{"granularity":"admin","points":[{"ref":"row_0001","lat":35.681240,"lon":139.767120},{"lat":35.695800,"lon":139.751400}]}'
```

**リクエスト例**
```json
{
  "granularity": "admin",
  "points": [
    {"ref": "row_0001", "lat": 35.681240, "lon": 139.767120},
    {"lat": 35.695800, "lon": 139.751400}
  ]
}
```

**レスポンス例**
```json
{
  "granularity": "admin",
  "results": [
    {
      "ref": "row_0001",
      "code": "13101",
      "address": "東京都千代田区"
    },
    {
      "code": null,
      "address": null
    }
  ]
}
```

### `POST /tools/summarize_stays`

タイムスタンプ付き位置情報サンプルから滞在セグメントを生成します。
連続するサンプルで同じコードを持つものをまとめ、コード・住所・滞在時間を返します。
解決できないサンプルは `code` と `address` が `null` となり、滞在はその前後で分割されます。

**curl ワンライナー**
```bash
curl -X POST http://localhost:8080/mcp/tools/summarize_stays \
  -H "Content-Type: application/json" \
  -d '{"positions":[{"timestamp":0,"lat":35.0,"lon":135.0},{"timestamp":60,"lat":35.0,"lon":135.0}]}'
```

**リクエスト例**
```json
{
  "positions": [
    {"timestamp": 0, "lat": 35.0, "lon": 135.0},
    {"timestamp": 60, "lat": 35.0, "lon": 135.0}
  ]
}
```

**レスポンス例**
```json
{
  "results": [
    {
      "start_ts": 0,
      "end_ts": 60,
      "code": "13101",
      "address": "東京都千代田区",
      "duration_sec": 60,
      "count": 2
    }
  ]
}
```

### 無効サンプルと解決不能サンプル

- **無効サンプル**: フィールド不足や非数値座標など、位置情報自体が不正。処理は停止し、`results` を含まない `INVALID_INPUT` エラーが返されます。
- **解決不能サンプル**: 位置情報は有効だが、Galuchat が行政区を解決できない場合。`code` と `address` が `null` のままレスポンスに含まれ、他のサンプルは通常通り処理されます。

## エラーモデル

```json
{
  "error": {
    "code": "INVALID_INPUT|API_ERROR|OUT_OF_COVERAGE|RATE_LIMIT|INTERNAL",
    "message": "..."
  }
}
```

## ChatGPT互換API (JSON-RPC)

ChatGPTクライアントと通信するために、JSON-RPC 2.0 形式のエンドポイントを提供します。

### `POST /rpc`

#### `tools/list`
利用可能なツール一覧を返します。

**リクエスト例**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/list"
}
```

**レスポンス例**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      {"name": "resolve_points", "description": "Resolve coordinates to district code and address.", "input_schema": {...}, "output_schema": {...}},
      {"name": "summarize_stays", "description": "Group consecutive positions by region code.", "input_schema": {...}, "output_schema": {...}}
    ]
  }
}
```

#### `tools/call`
ツールを実行します。`params.name` にツール名、`params.arguments` に引数を指定します。

**リクエスト例**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "resolve_points",
    "arguments": {"granularity": "admin", "points": [{"lat": 35.0, "lon": 135.0}]}
  }
}
```

**レスポンス例**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "granularity": "admin",
    "results": [
      {"code": "00000", "address": "Example"}
    ]
  }
}
```

## テスト

```bash
./vendor/bin/phpunit
```
