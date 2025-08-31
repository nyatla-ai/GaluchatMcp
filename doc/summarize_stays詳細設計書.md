# summarize_stays 詳細設計書（MCP 内部集計）

> 目的：GPS ログから抽出した滞在レコード `stays` を要約し、LLM が旅行記などを生成しやすい形式に整形する。

---

## 1. スコープ／用語

* **対象**：`summarize_stays`（入力：滞在レコード配列、出力：要約文字列と滞在一覧）。
* **stay**：単一地区への滞在。`code` と `name` で地区を表し、`start_ts`/`end_ts` で期間を示す。
* **granularity**：地区コードの粒度（`admin`：行政区域、`estat`：統計小地域、`jarl`：JCC/JCG）。
* **duration**：`end_ts - start_ts` の秒数。`start_ts` または `end_ts` が欠ける場合は `null`。

---

## 2. MCP 入出力 I/F（クライアント契約）

### 2.1 リクエスト

```jsonc
{
  "granularity": "admin|estat|jarl",   // 省略時は "admin"
    "stays": [
    { "ref?": "string<=128", "code": "string", "name": "string", "start_ts?": "RFC3339", "end_ts?": "RFC3339" }
    ],
    "mode?": "sequence|aggregate"       // 省略時は "sequence"
}
```

* `ref`：任意の参照ラベル。**MCP 内だけで使用**。
* `mode`：`sequence` は滞在順に、`aggregate` は地区ごとの合計滞在時間をまとめる。
* `start_ts`/`end_ts`：省略時は `duration` を算出せず `null` を返す。

### 2.2 レスポンス（統一ラップ）

```jsonc
{
  "granularity": "admin|estat|jarl",
  "summary": "string",
  "results": [
    { "ref?": "...", "code": "string", "name": "string", "duration_sec?": number }
  ],
    "errors": [
    { "ref?": "...", "reason": "INVALID_INPUT|INVALID_REF|MISSING_CODE" }
    ]
}
```

* `summary`：滞在の概略文（例："千代田区に2時間滞在→中央区に30分滞在"）。
* `results`：入力順を保持し、`duration_sec` は `mode` に応じて算出。

---

## 3. 集計ロジック（仕様）

1. **入力検証**：`ref` 長さ・文字集合、`code`/`name` の必須確認、時刻形式をチェック。失敗レコードは `errors` に即時格納。
2. **mode 決定**：省略時 `sequence`。
3. **滞在整列**：`sequence` は入力順維持、`aggregate` は `code` ごとに合算。
4. **duration 算出**：時刻が揃っている場合のみ `end_ts - start_ts` を計算。
5. **要約生成**：`mode` に応じて文章または一覧を組み立て、`summary` に格納。

---

## 4. エラー仕様（MCP 側）

| 種別              | 原因                          | 取扱い                           |
| ----------------- | ----------------------------- | -------------------------------- |
| `INVALID_INPUT`   | 必須フィールド欠落・時刻形式不正 | 当該レコードを `errors` に格納      |
| `INVALID_REF`     | 参照ラベルの長さ・文字集合逸脱     | `ref` を無視（必要なら errors へ） |
| `MISSING_CODE`    | `code` が空または無効           | 当該レコードを `errors` に格納      |

> 備考：エラー発生時も非エラーのレコード処理は継続する。

---

## 5. 性能・運用

* 入力規模は数千レコードを想定。メモリ上で完結する同期処理。
* 要約文生成はテンプレートベースで行い、LLM への委譲は別工程とする。
* ログには地区コードと滞在時間の統計のみを保存し、個別の時刻は保持しない。

