# summarize_stays 詳細設計書（位置サンプル→滞在抽出）

## 1. スコープと用語
- **position**: 単一観測点 `{timestamp, lat, lon}`。時刻は秒単位の数値。
- **stay**: 連続した position が同一の地区コードに属する区間。`start_ts`, `end_ts`, `code`, `duration_sec` を持つ。

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
    {"start_ts": number, "end_ts": number, "code": string, "duration_sec": number}
  ]
}
```
- `results` は検出順。`code` は地区コード。
- 無効サンプルは出力に含めず処理を継続する。

## 3. 処理フロー
1. **入力検証**: `timestamp` が単調増加かつ数値であること、`lat`/`lon` が数値であることを確認。
2. **地区コード解決**: 位置サンプルを Galuchat API に送り、対応する地区コードを取得。
3. **クラスタリング**: 連続して同じコードが続く区間を滞在として確定。2サンプル未満の区間は破棄。
4. **集計**: 採用クラスタから `start_ts`/`end_ts`/`code`/`duration_sec` を算出し `results` に push。

## 4. 無効サンプルの扱い
| reason            | 説明                                 |
|-------------------|--------------------------------------|
| `INVALID_COORD`   | 緯度経度が数値でない                |
| `INVALID_TIMESTAMP` | `timestamp` が数値でない、または逆順 |

不正サンプルは検証で除外し、出力には含めない。残りのサンプルの処理は継続する。

## 5. 性能・運用
- 入力規模は数千サンプルを想定し、線形アルゴリズムで処理。
- 位置データは結果にのみ使用し、サーバーには永続保存しない。
