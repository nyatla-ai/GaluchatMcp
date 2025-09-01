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
      "code": string,
      "address": string,
      "duration_sec": number,
      "count": number
    }
  ]
}
```
  - `results` は検出順。`code` は地区コード、`address` はその住所表記、`count` は滞在に含まれるサンプル数。
  - 入力検証または GaluchatAPI 応答で問題が生じた場合は `results` を返さず、`{error:{code,message,location?}}` を返す。

## 3. 処理フロー
1. **入力検証**: `timestamp` が単調増加かつ数値であること、`lat`/`lon` が数値であることを確認。不正があれば該当サンプルのインデックスを `location` に含めた `INVALID_INPUT` エラーを返し処理を終了する。
2. **地区コード解決**: 位置サンプルを Galuchat API に送り、対応する地区コードと住所を取得。
3. **クラスタリング**: 連続して同じコードが続く区間を滞在として確定。1サンプルのみの区間も滞在として扱う。
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
