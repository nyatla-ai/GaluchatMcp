# summarize_stays 詳細設計書（位置サンプル→滞在抽出）

## 1. スコープと用語
- **position**: 単一観測点 `{timestamp, lat, lon}`。時刻は秒単位の数値。
- **stay**: 連続した position のクラスタから得られる滞在区間。`start_ts`, `end_ts`, `center`, `duration_sec` を持つ。
- **params**: 閾値設定。`distance_threshold_m` はクラスタ判定距離、`duration_threshold_sec` は採用最小滞在時間。

## 2. MCP 入出力
### 2.1 リクエスト
```jsonc
{
  "positions": [
    {"timestamp": number, "lat": number, "lon": number}
  ],
  "params?": {
    "distance_threshold_m?": number,
    "duration_threshold_sec?": number
  }
}
```
- `positions` は時刻昇順。
- 閾値を省略した場合、距離 50m・時間 120 秒を採用。

### 2.2 レスポンス
```jsonc
{
  "results": [
    {"start_ts": number, "end_ts": number, "center": {"lat": number, "lon": number}, "duration_sec": number}
  ]
}
```
- `results` は検出順。`center` はクラスタ内の平均座標。
- 無効サンプルは出力に含めず処理を継続する。

## 3. 処理フロー
1. **入力検証**: `timestamp` が単調増加かつ数値であること、`lat`/`lon` が数値であることを確認。
2. **クラスタリング**: 先頭から順に処理し、距離閾値を超えた時点でクラスタを確定。確定クラスタの滞在時間が閾値未満なら破棄。
3. **集計**: 採用クラスタから `start_ts`/`end_ts`/`center`/`duration_sec` を算出し `results` に push。

## 4. 無効サンプルの扱い
| reason            | 説明                                 |
|-------------------|--------------------------------------|
| `INVALID_COORD`   | 緯度経度が数値でない                |
| `INVALID_TIMESTAMP` | `timestamp` が数値でない、または逆順 |

不正サンプルは検証で除外し、出力には含めない。残りのサンプルの処理は継続する。

## 5. 性能・運用
- 入力規模は数千サンプルを想定し、線形アルゴリズムで処理。
- 位置データは結果にのみ使用し、サーバーには永続保存しない。
