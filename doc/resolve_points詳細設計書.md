# resolve\_points 詳細設計書（MCP × GaluchatAPI準拠）

> 目的：MCP サーバのコア機能 `resolve_points` を、**GaluchatAPI 既存仕様に準拠**し、かつ **mapset は MCP 側の設定（PHP include）で制御**する方式で定義する。コード生成（実装）は別工程（Codeex）で行うため、本書では**仕様整合性と外部契約の明確化**を最優先とする。

---

## 1. スコープ／用語

* **対象**：`resolve_points`（入力：座標点の配列、出力：逆ジオ結果）。
* **外部API**：GaluchatAPI（逆ジオ系・画像系）。本機能で直接利用するのは **逆ジオ系 API**。
* **granularity**：返却コードの種類（`admin`：行政区域、`estat`：統計小地域、`jarl`：JCC/JCG）。
* **mapset**：GaluchatAPI のデータ版・解像度セット。**API の URL クエリパラメータ**として指定可（値は GaluchatAPI 側で定義）。
* **unit**：GaluchatAPI の整数座標スケーリング係数（API 仕様）。
* **入力点数上限**：デフォルトで約 10,000 点（設定で変更可）。

---

## 2. 外部APIとの契約（参照）

`granularity` によって、呼び出すエンドポイントを切替える。

| granularity | API エンドポイント（POST） | 役割（返却コード）        |
| ----------- | ----------------- | ---------------- |
| `admin`     | `/raacs`          | 行政区域コード（市区町村等）   |
| `estat`     | `/resareas`       | 統計小地域コード（町丁・字等）  |
| `jarl`      | `/rjccs`          | JCC/JCG コード（市・郡） |

**POST ボディの共通骨子**（API 仕様）

`mapset` は URL クエリパラメータとして指定する。

```jsonc
{
  "unit": <number>,
  "points": [ [<lon_int>, <lat_int>], ... ]
}
```

* `points` は **\[経度, 緯度] の整数ペア配列**。
* `unit` は **度→整数** のスケール（例：`0.001` なら `lon_int = round(lon / 0.001)`）。
* `mapset` は **API 側で定義済みの識別子**（granularity に適合するものを指定）で、URL クエリ `?mapset=` として渡す。

> 注意：各 API が受け付ける `mapset` の種類（AACODE/ESCODE 等）は **GaluchatAPI 仕様の制約に従う**。MCP は **granularity ごとに適合した mapset** のみを URL クエリとして付加する。

---

## 3. MCP 入出力 I/F（クライアント契約）

### 3.1 リクエスト

```jsonc
{
  "granularity": "admin|estat|jarl",   // 省略時は "admin"
    "points": [
    { "ref?": "string|null<=128", "lat": number, "lon": number }
    ]
}
```

* `ref`：任意の参照ラベル。**MCP 内だけで使用**。空文字や `null` を許容し、API には送らない。
* 座標系：WGS84。**有効範囲は GaluchatAPI が判定する**。
* 入力点数：最大 10,000 点程度（設定ファイルで変更可）。

### 3.2 レスポンス（統一ラップ）

```jsonc
{
  "granularity": "admin|estat|jarl",
  "results": [
    { "ref?": "string|null", "code": "string|null", "address": "string|null" }
  ]
}
```

* `results` は全点が成功した場合のみ返却する。1点でもエラーが発生した場合は `results` を返さず、`{"error": {"code", "message", "location?"}}` 形式の単一エラー応答となる。
* `lat` と `lon` は出力に含めない。入力の `ref` または配列順で照合する。
* `ref` が入力に存在する場合のみ各要素に含める。省略された場合は対応する要素から `ref` を省略する。
* `results` は入力点と一対一で対応し、**入力順・件数を保持し、圧縮は行わない**。
* GaluchatAPI が `null` を返す場合は `code` と `address` を共に `null` として保持する。

### 3.3 無効入力とカバー外の違い
- **無効入力**: `ref` の長さ超過や `lat`/`lon` が数値でないなど、リクエストの形式が仕様に反する場合。
  `INVALID_INPUT` エラーを返し、`results` は返さない。
- **カバー外**: 入力は正しいが GaluchatAPI が地区コードを返さない場合。該当要素の `code` と `address` を `null` に設定して
  `results` に含める。

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
  'unit'       => 0.001            // GaluchatAPI の unit（度→整数）
  ],
  'resolve_points' => [
    'max_points' => 10000          // リクエストあたりの入力点上限
  ]
];
```

**制約**：

* `mapsets.admin`/`mapsets.jarl` は **AACODE 系の mapset 名**を設定（例：`ma1000`/`ma10000`）。
* `mapsets.estat` は **ESCODE 系の mapset 名**を設定（例：`estatremap10000`）。
* 値は公式ドキュメントに掲載の有効名のみを使用。
* これらの値は API 呼び出し時に URL クエリ `?mapset=` として利用され、POST ボディには含めない。

**セキュリティ**：

* Web ルート外・書込不可（600/640）。副作用コードを含めない（return 配列のみ）。
* OPcache 有効化推奨。ロード時に必須キーの存在と型を検証。

---

## 5. 処理フロー（仕様）

1. **入力検証**：全点について `ref` 長さ・文字集合、および `lat/lon` の数値範囲を確認。いずれかが不正な場合は該当要素のインデックスまたは `ref` を `location` に含めた `INVALID_INPUT` エラーを返し処理を中断する。
2. **granularity 決定**：省略時 `admin`。
 3. **設定読込**：`base_url`、`mapset[granularity]`、`unit` を取得。
 4. **リクエスト構築**：

     * `unit` をボディに付与。
     * `points` は `[ round(lon/unit), round(lat/unit) ]` 整数ペア列。
     * `mapset` はクエリパラメータとして URL に付加。
5. **API 呼び出し**：エンドポイントは granularity に応じて `/raacs`、`/resareas`、`/rjccs`。`mapset` を含む URL へ POST し、タイムアウトは設定値。
6. **エラー処理**：

    * HTTP 429 → `RATE_LIMIT` エラーを返却。
    * HTTP 4xx/5xx → ステータスとボディを含む `API_ERROR` を返却。
7. **応答マージ**：GaluchatAPI 応答は共通して `{addresses:{<code>:<obj>}, aacodes|scodes:[<code|null>,...]}` 形式で返る。`admin`/`jarl` は `aacodes`、`estat` は `scodes` を用いる。各インデックスのコード値から `addresses[code]` を参照し、`admin`/`estat` は配列値をそのまま `code` に、`jarl` は辞書側の `code` を `code` に設定する。地名フィールドを連結して `address` 文字列を作成し、`{ref?, code, address}` を `results` に追加する。コードが `null` の場合は `address` も `null` とした要素を `results` に含める。応答件数が入力と一致しない、またはコードと `addresses` の対応が取れない場合は該当点を特定し `OUT_OF_COVERAGE` エラーを返す。
8. **応答返却**：`granularity` と `results` を返す。
---

## 6. エラー仕様（MCP 側）

| 種別             | 原因                                          |
|------------------|-----------------------------------------------|
| `INVALID_INPUT`  | `ref` 長さ・文字集合違反、座標レンジ外などの入力エラー |
| `API_ERROR`      | GaluchatAPI からの 4xx/5xx 応答              |
| `RATE_LIMIT`     | HTTP 429（レート制限）                       |
| `OUT_OF_COVERAGE`| GaluchatAPI 応答の不整合や欠落               |
| `INTERNAL`       | サーバー内部エラー                            |

`code` と `address` が同時に `null` の要素はエラーではなく、そのまま `results` に含める。

いずれのエラーでも `results` は返さず、`{error:{code,message,location?}}` を返却する。`location` には問題があった点のインデックスや `ref` を含める。

> 備考：API からの **意味的エラー（mapset不適合など）** は `API_ERROR` に包含し、ログには `status` やメッセージを保存。

---

## 7. 性能・運用

* 単一リクエストで全入力を処理する。タイムアウト値のみ適切に設定する。

---

## 8. テスト観点（受け入れ基準）

1. **API切替**：`granularity` ごとに正しいエンドポイントへ送信される。
2. **mapset透過**：設定の `mapset` が URL クエリに正しく付加される（admin/jarl=AACODE、estat=ESCODE）。
3. **unit丸め**：境界近傍の丸め誤差でも逆転しない（許容差の定義）。
4. **レート超過**：429 を受けた場合に `RATE_LIMIT` エラー応答になる。
5. **件数保持**：同一地点が続く場合でも結果数が入力数と一致する。


---

## 9. セキュリティ／可用性

* 設定ファイルは **return 配列のみ**（副作用禁止）。
* 機微値（API鍵など）は **別ファイル**または環境変数で扱い、公開リポに含めない。
* タイムアウトを適切に設定し、API 側の一時障害に備える。

---

## 10. 変更容易性（将来拡張）

* **mapset の切替**：設定ファイルの値変更のみで反映（デプロイ単位）。
* **API拡張**：将来 `options` を I/F に追加し、`mapset` 上書きや API 情報のミラーリングを許容可能（デフォルト無効）。
* **メタ情報**：API が返すデータ版などがある場合、`payload` に保持。必要時のみ `meta` にミラー（前方互換）。

---

## 付録A：入出力サンプル（概形）

### 入力

```jsonc
{
  "granularity": "admin",
  "points": [
    { "ref": "p1", "lat": 35.68283, "lon": 139.75945 },
    { "lat": 35.6895,  "lon": 139.6917 },
    { "ref": "p3", "lat": 0.0,      "lon": 0.0 }
  ]
}
```

### 内部POST（/raacs?mapset=ma10000）

```jsonc
{
  "unit": 0.001,
  "points": [ [139760, 35683], [139692, 35690], [0, 0] ]
}
```

### 内部応答（例）

```jsonc
{
  "addresses": {
    "131010001": { "prefecture": "東京都", "city": "千代田区" },
    "131040001": { "prefecture": "東京都", "city": "新宿区" }
  },
  "aacodes": [131010001, 131040001, null]
}
```

### 出力（概形）

```jsonc
{
  "granularity": "admin",
  "results": [
    { "ref": "p1", "code": "131010001", "address": "東京都千代田区" },
    { "code": "131040001", "address": "東京都新宿区" },
    { "ref": "p3", "code": null, "address": null }
  ]
}
```

> 注：GaluchatAPI の `/raacs` 応答は `addresses` 辞書と `aacodes` 配列で構成されるため、MCP はコード配列の順に対応する地名を辞書から取得して `results` を構築する。
