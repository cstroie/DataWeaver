# DataWeaver

A minimal Model Communication Protocol server implemented in PHP with functions to return the current time, fetch webpage content, and get weather information.

## Setup

1. Rename `config.php.template` to `config.php`
2. Edit `config.php` and add your OpenWeatherMap API key

## Usage

Send a POST request to the server with JSON data:

### Get Current Time
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

### Get Webpage Text Content
```json
{
  "function": "get_webpage_text",
  "url": "https://example.com"
}
```

The server will respond with:

```json
{
  "result": "Example Domain This domain is for use in illustrative examples in documents..."
}
```

### Get Weather Information
```json
{
  "function": "get_weather",
  "city": "London"
}
```

The server will respond with:

```json
{
  "result": {
    "city": "London",
    "country": "GB",
    "temperature": 15.5,
    "description": "light rain",
    "humidity": 72,
    "pressure": 1012
  }
}
```

### Get METAR Information
```json
{
  "function": "get_metar",
  "icao": "EGLL"
}
```

The server will respond with:

```json
{
  "result": {
    "icao": "EGLL",
    "metar": "EGLL 151420Z 27006KT 9999 FEW025 15/07 Q1013"
  }
}
```

## Example using curl

### Get Current Time
```bash
curl -X POST -H "Content-Type: application/json" -d '{"function":"get_current_time"}' http://localhost/
```

### Get Webpage Text Content
```bash
curl -X POST -H "Content-Type: application/json" -d '{"function":"get_webpage_text","url":"https://example.com"}' http://localhost/
```

### Get Weather Information
```bash
curl -X POST -H "Content-Type: application/json" -d '{"function":"get_weather","city":"London"}' http://localhost/
```

### Get METAR Information
```bash
curl -X POST -H "Content-Type: application/json" -d '{"function":"get_metar","icao":"EGLL"}' http://localhost/
```

## Functions

- `get_current_time`: Returns the current server time in YYYY-MM-DD HH:MM:SS format
- `get_webpage_text`: Fetches the content of a webpage and returns it as plain text
- `get_weather`: Returns current weather information for a specified city
- `get_metar`: Returns METAR information for a specified ICAO airport code

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0).

## Configuration

To use the weather function, you need to:

1. Rename `config.php.template` to `config.php`
2. Edit `config.php` and replace `your_openweather_api_key_here` with your actual API key from [OpenWeatherMap](https://openweathermap.org/api)
