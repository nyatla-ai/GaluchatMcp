# Galuchat MCP Server

Minimal MCP server that resolves coordinates to administrative district information.

## Requirements

- PHP 8.2+
- Composer

## Installation

```bash
composer install
```

Copy `.env.example` to `.env` and adjust values:

```
GALUCHAT_BASE_URL=https://galuchat.example.com
TIMEOUT_MS=3000
```

## Running

Using PHP built-in server:

```bash
php -S localhost:8080 -t app/public
```

## Endpoints

### `GET /mcp/manifest`
Returns manifest describing available tools.

### `POST /tools/resolve_points`
Resolve points to district codes. Each input point yields an element in
`results`, preserving order. Entries echo the original `ref` (if any) along with
the resolved `code` and `address` (both may be `null`). The `ref` field is
optional; it may be omitted or set to an empty string or `null`. When omitted in
the request, it is not included in the corresponding result.

Request body:
```json
{
  "granularity": "admin",
  "points": [
    {"ref": "row_0001", "lat": 35.681240, "lon": 139.767120},
    {"lat": 35.695800, "lon": 139.751400}
  ]
}
```

Response body:
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
Generate stay segments from timestamped position samples. The server resolves
each position to a region code and groups consecutive samples that share the
same code, returning stay periods with their codes, addresses, and durations.
Samples whose district codes cannot be resolved have `code` and `address`
set to `null`, and stays split around these unresolved samples.

Request body:
```json
{
  "positions": [
    {"timestamp": 0, "lat": 35.0, "lon": 135.0},
    {"timestamp": 60, "lat": 35.0, "lon": 135.0}
  ]
}
```

Response body:
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

### Invalid vs unresolvable samples
These rules apply to both `resolve_points` and `summarize_stays`:
- **Invalid sample**: the position object itself is malformed (e.g. missing
  fields or non-numeric coordinates). The server stops processing and returns
  an `INVALID_INPUT` error without any `results`.
- **Unresolvable sample**: the sample is valid but Galuchat cannot resolve a
  district. The response still includes the sample, with its `code` and
  `address` set to `null`, and other samples are processed normally.

## Error model

```
{
  "error": {
    "code": "INVALID_INPUT|API_ERROR|OUT_OF_COVERAGE|RATE_LIMIT|INTERNAL",
    "message": "..."
  }
}
```

## Tests

```bash
./vendor/bin/phpunit
```
