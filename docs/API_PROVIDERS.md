# API Providers

## Implemented Connectors

### Football-Data.org

- Slug: `football-data`
- Base URL: `https://api.football-data.org/v4`
- Docs: `https://www.football-data.org/documentation/quickstart`
- Default status: inactive
- Default minute limit: 10
- Auth header: `X-Auth-Token`
- Test endpoint: `GET /competitions`
- Sync leagues: `GET /competitions`
- Sync matches: `GET /competitions/{competition_code}/matches?season={year}`

### API-Football / API-Sports

- Slug: `api-football`
- Base URL: `https://v3.football.api-sports.io`
- Docs: `https://www.api-football.com/documentation-v3`
- Default status: inactive
- Auth header: `x-apisports-key`
- Test endpoint: `GET /status`
- Sync leagues: `GET /leagues`
- Sync teams: `GET /teams?league={league_id}&season={year}`
- Sync matches: `GET /fixtures?league={league_id}&season={year}&timezone=America/Sao_Paulo`
- Sync standings: `GET /standings?league={league_id}&season={year}`
- Sync statistics: `GET /fixtures/statistics?fixture={fixture_id}`

## Planned Providers

- SportMonks
- TheSportsDB
- OpenLigaDB
- Sportradar
- Stats Perform/Opta
- Genius Sports

These appear in the admin as planned providers and can be configured with URLs, developer portals, and rate limits before a real adapter is added.

## API Keys

Keys are stored in `api_provider_keys.encrypted_key` using Laravel encryption. The admin and APIs only show `key_hint`.

## Rate Limits

The system checks:

- Provider `rate_limit_per_minute`
- Provider `rate_limit_per_day`
- Key `requests_per_minute`
- Key `requests_per_day`
- Key cooldown after external `429` or manual cooldown

Every external request should be logged through `ApiRequestLogger`.

Request logs generated during sync now include `sync_job_id`, so an operator can trace external calls from a specific job detail page.

## Retry and Pagination

Temporary errors are retried up to three times:

- timeout or connection error
- HTTP 429
- HTTP 500, 502, 503, 504

HTTP 429 places the key in cooldown. If another active key is available, the next attempt can use it. API-Football endpoints that return `paging` continue page collection while `current < total`, bounded by `max_pages`.

Provider sync jobs are executed asynchronously by `RunSportsDataSyncJob` when triggered from the admin or scheduler. The queue job itself uses `tries = 1`; provider HTTP retry/backoff handles transient API failures.

Example paginated config:

```json
{
  "league_id": 71,
  "season": 2024,
  "timezone": "America/Sao_Paulo",
  "page": 1,
  "max_pages": 10
}
```

Example incremental config:

```json
{
  "league_id": 71,
  "season": 2024,
  "updated_since": "2026-05-01T00:00:00"
}
```

HTTP 429 places the key in cooldown. HTTP 401/403 writes a clear authentication error to the key. cURL error 60 is logged as:

```txt
SSL certificate validation failed. Check CA certificates on the server.
```

## Normalization

Provider data is normalized through `SportsDataNormalizer`.

- Football is created if missing.
- Countries are upserted by code when available, otherwise by name.
- Leagues, seasons, teams, matches, and standings create `provider_mappings`.
- Matches are upserted by `provider_id + external_id`; if no external ID is available, the fallback is league, season, home team, away team, and start time.
- Provider API keys are never written to logs.

## Adding a Provider

1. Seed or create an `api_providers` row.
2. Create a class under `app/Services/SportsData/Providers`.
3. Implement `SportsDataProviderInterface`.
4. Register the slug in `ProviderRegistry`.
5. Add tests for connection, rate limit, logging, and sync behavior.
