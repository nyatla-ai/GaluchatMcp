# summarize_stays 詳細設計書（位置サンプル→滞在抽出）

## 1. スコープと用語
- **position**: 単一観測点 `{timestamp, lat, lon}`。時刻は秒単位の数値。
- **stay**: 連続した position が同一の地区コードに属する区間。`start_ts`, `end_ts`, `code`, `address`, `duration_sec`, `count` を持つ。

## 2. MCP 入出力
### 2.1 リクエスト
```jsonc
{
  "positions": [
    {"timestamp": number, "lat": number, "lon": number}
  ]
}
```
- `positions` は時刻昇順。

### 2.2 レスポンス
```jsonc
{
  "results": [
    {
      "start_ts": number,
      "end_ts": number,
      "code": string|null,
      "address": string|null,
      "duration_sec": number,
      "count": number
    }
  ]
}
```
  - `results` は検出順。`code` は地区コード、`address` はその住所表記、`count` は滞在に含まれるサンプル数。
  - GaluchatAPI が null を返した場合、対応する `code` と `address` も null として返却する。
  - 入力検証または GaluchatAPI 応答で問題が生じた場合は `results` を返さず、`{error:{code,message,location?}}` を返す。

### 2.3 無効サンプルと未解決サンプルの扱い
- **無効サンプル**: `timestamp` や `lat`/`lon` が数値でない、時系列順でないなど、入力が仕様に合致しない場合。検出した時点で
  `INVALID_INPUT` エラーを返し、処理を終了する。
- **未解決サンプル**: 入力は正しいが GaluchatAPI が地区コードを返せない場合。`code` と `address` を `null` に設定したまま処理を
  続行し、前後のサンプルとは別の滞在として扱う。

## 3. 処理フロー
1. **入力検証**: `timestamp` が単調増加かつ数値であること、`lat`/`lon` が数値であることを確認。不正があれば該当サンプルのインデックスを `location` に含めた `INVALID_INPUT` エラーを返し処理を終了する。
2. **地区コード解決**: 位置サンプルを Galuchat API に送り、対応する地区コードと住所を取得。GaluchatAPI が `null` を返したサンプルは `code` と `address` に `null` を設定する。
3. **クラスタリング**: 連続して同じコードが続く区間を滞在として確定。`code` が `null` のサンプルも滞在として扱い、前後でクラスタが分かれる。1サンプルのみの区間も滞在として扱う。
4. **集計**: 各クラスタから `start_ts`/`end_ts`/`code`/`address`/`duration_sec`/`count` を算出し `results` に push。

## 4. エラー仕様

| code             | 原因                                                        |
|------------------|-------------------------------------------------------------|
| `INVALID_INPUT`  | `timestamp` の数値/順序不正、`lat`/`lon` の数値不正など入力エラー |
| `API_ERROR`      | GaluchatAPI からのエラー応答                                 |
| `OUT_OF_COVERAGE`| GaluchatAPI 応答の不整合                                     |
| `RATE_LIMIT`     | HTTP 429（レート制限）                                      |
| `INTERNAL`       | サーバー内部エラー                                          |

エラー時は `results` を返さず `{"error": {"code", "message", "location?"}}` 形式で返却する。`location` には問題のサンプルのインデックスなどを含める。

## 5. 性能・運用
- 入力規模は数千サンプルを想定し、線形アルゴリズムで処理。
- 位置データは結果にのみ使用し、サーバーには永続保存しない。
## 付録A：入出力サンプル（null コードを含む例）

### 入力
```jsonc
{
  "positions": [
    {"timestamp": 0, "lat": 35.68283, "lon": 139.75945},
    {"timestamp": 10, "lat": 0.0, "lon": 0.0},
    {"timestamp": 20, "lat": 35.68283, "lon": 139.75945}
  ]
}
```

### GaluchatAPI 応答（例）
```jsonc
{
  "addresses": {
    "131010001": { "prefecture": "東京都", "city": "千代田区" }
  },
  "aacodes": [131010001, null, 131010001]
}
```

### 地区コード解決後のサンプル
```jsonc
[
  {"timestamp": 0, "code": "131010001", "address": "東京都千代田区"},
  {"timestamp": 10, "code": null, "address": null},
  {"timestamp": 20, "code": "131010001", "address": "東京都千代田区"}
]
```

### 出力（null コードを含む）
```jsonc
{
  "results": [
    {
      "start_ts": 0,
      "end_ts": 0,
      "code": "131010001",
      "address": "東京都千代田区",
      "duration_sec": 0,
      "count": 1
    },
    {
      "start_ts": 10,
      "end_ts": 10,
      "code": null,
      "address": null,
      "duration_sec": 0,
      "count": 1
    },
    {
      "start_ts": 20,
      "end_ts": 20,
      "code": "131010001",
      "address": "東京都千代田区",
      "duration_sec": 0,
      "count": 1
    }
  ]
}
```

> サンプル2は `code` と `address` が `null` に設定され、単独の滞在として出力される。
