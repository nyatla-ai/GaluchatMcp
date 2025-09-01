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
`results`, preserving order. Entries contain the original `ref` (if any), a
`success` flag, and either a `payload` with `code` and `address` or an `error`
object describing the failure.

Request body:
```json
{
  "granularity": "admin",
  "points": [
    {"ref": "row_0001", "lat": 35.681240, "lon": 139.767120},
    {"ref": "row_0002", "lat": 35.695800, "lon": 139.751400}
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
      "success": true,
      "payload": {
        "code": "13101",
        "address": "東京都千代田区"
      }
    },
    {
      "ref": "row_0002",
      "success": false,
      "error": {
        "code": "OUT_OF_COVERAGE",
        "message": "OUT_OF_COVERAGE"
      }
    }
  ]
}
```

### `POST /tools/summarize_stays`
Generate stay segments from timestamped position samples. The server clusters
positions by distance and time thresholds and returns stay periods with their
centers and durations.

Request body:
```json
{
  "positions": [
    {"timestamp": 0, "lat": 35.0, "lon": 135.0},
    {"timestamp": 60, "lat": 35.0, "lon": 135.0005}
  ],
  "params": {"distance_threshold_m": 100, "duration_threshold_sec": 60}
}
```

Response body:
```json
{
  "results": [
    {
      "start_ts": 0,
      "end_ts": 60,
      "center": {"lat": 35.0, "lon": 135.00025},
      "duration_sec": 60
    }
  ],
  "errors": []
}
```

## Error model

```
{
  "error": {
    "code": "INVALID_ARGUMENT|OUT_OF_RANGE|RATE_LIMIT|INTERNAL",
    "message": "..."
  }
}
```

## Tests

```bash
./vendor/bin/phpunit
```
