# PHP AI MCP Server

A minimal Model Communication Protocol server implemented in PHP with one function to return the current time.

## Usage

Send a POST request to the server with JSON data:

```json
{
  "function": "get_current_time"
}
```

The server will respond with:

```json
{
  "result": "2023-10-15 14:30:22"
}
```

## Example using curl

```bash
curl -X POST -H "Content-Type: application/json" -d '{"function":"get_current_time"}' http://localhost/
```

## Functions

- `get_current_time`: Returns the current server time in YYYY-MM-DD HH:MM:SS format
