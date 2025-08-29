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
Resolve points to district codes.

Request body:
```json
{
  "granularity": "admin",
  "points": [
    {"ref": "row_0001", "lat": 35.681240, "lon": 139.767120, "t": "2025-08-29T09:00:00Z"},
    {"ref": "row_0002", "lat": 35.695800, "lon": 139.751400}
  ]
}
```

Response body:
```json
{
  "results": [
    {"ref": "row_0001", "code": "13101", "name": "東京都千代田区"},
    {"ref": "row_0002", "code": "13102", "name": "東京都中央区"}
  ],
  "failed": [],
  "attribution": "Data via Galuchat API"
}
```

## Error model

```
{
  "error": {
    "code": "INVALID_ARGUMENT|OUT_OF_RANGE|RATE_LIMITED|INTERNAL",
    "message": "..."
  }
}
```

## Tests

```bash
./vendor/bin/phpunit
```
