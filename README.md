# README（日本語版）

最小限のMCPサーバーで、座標から行政区画情報を取得します

## エンドポイント
マニフェスト: https://nyatla.jp/galuchat-mcp/mcp/manifest

ツールエンドポイントは上記マニフェストを基準に `../tools/resolve_points`、`../tools/summarize_stays` としてアクセスします。


## 必要条件

- PHP 8.2 以上
- Composer

## インストール

```bash
composer install
```

`.env.example` を `.env` にコピーし、必要な値を設定します。
```
GALUCHAT_BASE_URL=https://galuchat.example.com
TIMEOUT_MS=3000
```

## 起動方法

PHP のビルトインサーバーで起動する場合:
```bash
php -S localhost:8080 -t app/public
```

## エンドポイント

### `GET /mcp/manifest`

利用可能なツールのマニフェストを返します。

以降で示すツールのエンドポイントは、このマニフェストを取得した URL を基準にした相対パスです。

**curl ワンライナー**
```bash
curl http://localhost:8080/mcp/manifest
```

### `POST ../tools/resolve_points`

位置情報の配列を行政区コードと住所に解決します。
各入力ポイントが順番に `results` に対応し、`ref`（任意）と解決された `code`・`address` を返します。

**curl ワンライナー**
```bash
curl -X POST http://localhost:8080/tools/resolve_points \
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

### `POST ../tools/summarize_stays`

タイムスタンプ付き位置情報サンプルから滞在セグメントを生成します。
連続するサンプルで同じコードを持つものをまとめ、コード・住所・滞在時間を返します。
解決できないサンプルは `code` と `address` が `null` となり、滞在はその前後で分割されます。

**curl ワンライナー**
```bash
curl -X POST http://localhost:8080/tools/summarize_stays \
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

## テスト

```bash
./vendor/bin/phpunit
```
