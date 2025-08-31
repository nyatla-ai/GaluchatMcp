# resolve\_points 詳細設計書（MCP × GaluchatAPI準拠）

> 目的：MCP サーバのコア機能 `resolve_points` を、**GaluchatAPI 既存仕様に準拠**し、かつ **mapset は MCP 側の設定（PHP include）で制御**する方式で定義する。コード生成（実装）は別工程（Codeex）で行うため、本書では**仕様整合性と外部契約の明確化**を最優先とする。

---

## 1. スコープ／用語

* **対象**：`resolve_points`（入力：座標点の配列、出力：逆ジオ結果）。
* **外部API**：GaluchatAPI（逆ジオ系・画像系）。本機能で直接利用するのは **逆ジオ系バッチAPI**。
* **granularity**：返却コードの種類（`admin`：行政区域、`estat`：統計小地域、`jarl`：JCC/JCG）。
* **mapset**：GaluchatAPI のデータ版・解像度セット。**API のパラメータ**として指定可（値は GaluchatAPI 側で定義）。
* **unit**：バッチAPIの整数座標スケーリング係数（API 仕様）。

---

## 2. 外部APIとの契約（参照）

`granularity` によって、呼び出す **バッチ版エンドポイント**を切替える。

| granularity | API エンドポイント（POST） | 役割（返却コード）        |
| ----------- | ----------------- | ---------------- |
| `admin`     | `/raacs`          | 行政区域コード（市区町村等）   |
| `estat`     | `/resareas`       | 統計小地域コード（町丁・字等）  |
| `jarl`      | `/rjccs`          | JCC/JCG コード（市・郡） |

**POST ボディの共通骨子**（API 仕様）

```jsonc
{
  "unit": <number>,
  "mapset": "<string>",
  "points": [ [<lon_int>, <lat_int>], ... ]
}
```

* `points` は **\[経度, 緯度] の整数ペア配列**。
* `unit` は **度→整数** のスケール（例：`0.001` なら `lon_int = round(lon / 0.001)`）。
* `mapset` は **API 側で定義済みの識別子**（granularity に適合するものを指定）。

> 注意：各 API が受け付ける `mapset` の種類（AACODE/ESCODE 等）は **GaluchatAPI 仕様の制約に従う**。MCP は **granularity ごとに適合した mapset** のみを設定から渡す。

---

## 3. MCP 入出力 I/F（クライアント契約）

### 3.1 リクエスト

```jsonc
{
  "granularity": "admin|estat|jarl",   // 省略時は "admin"
  "points": [
    { "ref?": "string<=128", "lat": number, "lon": number, "t?": "RFC3339" }
  ]
}
```

* `ref`：任意の参照ラベル。**MCP 内だけで使用**。API には送らない。
* `t`：任意。**受理のみ**（保存・解釈なし）。
* 座標系：WGS84。`lat ∈ [-90,90]`、`lon ∈ [-180,180]`。
* 入力点数：**上限は実装設定で制限**（API の 1 リクエスト上限 1000 件のバッチ分割を内部で行う）。

### 3.2 レスポンス（統一ラップ）

```jsonc
{
  "granularity": "admin|estat|jarl",
  "results": [
    {
      "ref?": "...", "lat": number, "lon": number,
      "ok": true,
      "payload": { /* GaluchatAPI の生レスポンス要素 */ }
    }
  ],
  "errors": [
    { "ref?": "...", "lat": number, "lon": number, "reason": "INVALID_COORD|API_ERROR|RATE_LIMIT|OUT_OF_COVERAGE|INVALID_REF|INVALID_T" }
  ]
}
```

* `payload` は **変形せず** API 応答を格納（前方互換性のため）。
* 入力順の保持：`preserve_order=true` 設定時、`results` を入力順へ整列。

---

## 4. 設定方式（PHP include）

### 4.1 配置と読み込み

* 設定ファイルは **Web ルート外**に配置し、`include` で **return 配列**を読み込む。
* 環境別に分割（例：`config/resolve_points/config.prod.php` ほか）。
* 実行環境は `GALUCHAT_ENV` 等の環境変数で切替。

### 4.2 設定スキーマ（論理）

```php
return [
  'galuchat' => [
    'base_url'   => 'https://... ', // GaluchatAPI ベースURL
    'timeout_ms' => 10000,
    'mapsets'    => [
      'admin' => 'ma10000',          // AACODE系（/raacs, /rjccs）
      'estat' => 'estatremap10000',  // ESCODE系（/resareas）
      'jarl'  => 'ma10000'           // AACODE系（/rjccs）
    ],
    'unit'       => 0.001            // バッチAPIの unit（度→整数）
  ],
  'batch' => [
    'max_points_per_request' => 1000, // API 上限に合わせる
    'parallel_requests'      => 4,    // レート制御とスループットのバランス
    'preserve_order'         => true
  ],
  'retry' => [ 'max_attempts' => 2, 'backoff_ms' => 300 ],
  'log'   => [ 'include_request_id' => true, 'include_mapset' => true ]
];
```

**制約**：

* `mapsets.admin`/`mapsets.jarl` は **AACODE 系の mapset 名**を設定（例：`ma1000`/`ma10000`）。
* `mapsets.estat` は **ESCODE 系の mapset 名**を設定（例：`estatremap10000`）。
* 値は **/apispec** や公式ドキュメントに掲載の有効名のみを使用。

**セキュリティ**：

* Web ルート外・書込不可（600/640）。副作用コードを含めない（return 配列のみ）。
* OPcache 有効化推奨。ロード時に必須キーの存在と型を検証。

---

## 5. 処理フロー（仕様）

1. **入力検証**：全点について `lat/lon` 範囲、`ref` 長さと文字集合、`t` 形式（RFC3339）を確認。失敗点は `errors` に即時格納。
2. **granularity 決定**：省略時 `admin`。
3. **設定読込**：`base_url`、`mapset[granularity]`、`unit`、バッチ/リトライ設定を取得。
4. **バッチ分割**：`max_points_per_request` 件で分割（例：1000件）。
5. **POST ボディ構築**：

   * `unit` を付与。
   * `mapset` に **設定値**をそのまま格納。
   * `points` は `[ round(lon/unit), round(lat/unit) ]` 整数ペア列。
6. **API 呼び出し**：エンドポイントは granularity に応じて `/raacs`、`/resareas`、`/rjccs`。タイムアウトは設定値。
7. **エラー処理**：

   * HTTP 429 → `RATE_LIMIT`（指数バックオフで規定回数まで再試行）。
   * HTTP 4xx/5xx → `API_ERROR`（規定回数で打切り）。
8. **応答マージ**：バッチごとの応答を入力点へ対応付け、`payload` に **生データ**として格納。
9. **順序整列**：指定時は入力順へ整列。
10. **応答返却**：`granularity`、`results`、`errors` を返す。

---

## 6. エラー仕様（MCP 側）

| 種別                | 原因                | 取扱い                       |
| ----------------- | ----------------- | ------------------------- |
| `INVALID_COORD`   | 経緯度の数値・範囲不正       | 当該点を `errors` に格納、以降処理しない |
| `INVALID_REF`     | 参照ラベルの長さ・文字集合逸脱   | `ref` を無視（必要なら errors へ）  |
| `INVALID_T`       | RFC3339 不一致       | `t` を無視（必要なら errors へ）    |
| `API_ERROR`       | 4xx/5xx 等サーバ応答エラー | リトライ規定後、当該点を `errors`     |
| `RATE_LIMIT`      | 429 応答            | バックオフ再試行後、失敗分は `errors`   |
| `OUT_OF_COVERAGE` | API 返却に基づくカバー外    | 当該点を `errors`             |

> 備考：API からの **意味的エラー（mapset不適合など）** は `API_ERROR` に包含。ログには `status`/メッセージを保存。

---

## 7. 性能・運用

* **並列度**は設定値で制御（既定：4）。レート制限（例：1s/10req）に抵触しないよう調整。
* **大規模入力**（例：1〜10万点）は 1000 件バッチで順次投入。タイムアウトと再試行により部分成功を許容。
* **監査**：起動時に `/apispec` を取得し、設定 `mapset` が有効一覧に存在するかを警告ログ出力（自動修正は行わない）。

---

## 8. テスト観点（受け入れ基準）

1. **API切替**：`granularity` ごとに正しいエンドポイントへ送信される。
2. **mapset透過**：設定の `mapset` がボディへ正しく入る（admin/jarl=AACODE、estat=ESCODE）。
3. **unit丸め**：境界近傍の丸め誤差でも逆転しない（許容差の定義）。
4. **分割挙動**：1001 点以上で 1000/1 の 2 バッチになる。
5. **順序保持**：`preserve_order` 有効時、`results` が入力順に一致。
6. **レート超過**：429 を受けてバックオフ→再試行→失敗時は `errors` に適切反映。
7. **部分成功**：一部バッチが失敗しても成功分を返す。
8. **設定異常**：mapset 未設定・型不整合で起動時エラー（Fail Fast）。

---

## 9. セキュリティ／可用性

* 設定ファイルは **return 配列のみ**（副作用禁止）。
* 機微値（API鍵など）は **別ファイル**または環境変数で扱い、公開リポに含めない。
* タイムアウト・リトライを適切に設定し、API 側の一時障害に耐性。

---

## 10. 変更容易性（将来拡張）

* **mapset の切替**：設定ファイルの値変更のみで反映（デプロイ単位）。
* **API拡張**：将来 `options` を I/F に追加し、`mapset` 上書きや `/apispec` の情報ミラーリングを許容可能（デフォルト無効）。
* **メタ情報**：API が返すデータ版などがある場合、`payload` に保持。必要時のみ `meta` にミラー（前方互換）。

---

## 付録A：入出力サンプル（概形）

### 入力

```jsonc
{
  "granularity": "estat",
  "points": [
    { "ref": "p1", "lat": 35.68283, "lon": 139.75945 },
    { "ref": "p2", "lat": 35.6895,  "lon": 139.6917 }
  ]
}
```

### 内部POST（/resareas）

```jsonc
{
  "unit": 0.001,
  "mapset": "estatremap10000",
  "points": [ [139760, 35683], [139692, 35690] ]
}
```

### 出力（概形）

```jsonc
{
  "granularity": "estat",
  "results": [
    { "ref": "p1", "lat": 35.68283, "lon": 139.75945, "ok": true, "payload": { /* API応答要素 */ } },
    { "ref": "p2", "lat": 35.6895,  "lon": 139.6917,  "ok": true, "payload": { /* API応答要素 */ } }
  ],
  "errors": []
}
```

> 注：`payload` の具体構造（コードや名称のフィールド名）は **API 原文どおり**とし、MCP では再マッピングしない。
