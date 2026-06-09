# FutIA Data Hub

FutIA Data Hub is a Laravel 11 + React data platform for sports API aggregation. The first version focuses on football while keeping the database, provider registry, sync pipeline, public API, and admin panel ready for multiple sports.

## Stack

- Laravel 11, PHP 8.2+
- MySQL 8 or MariaDB on aaPanel; SQLite works for local tests
- Laravel Scheduler, queues, migrations, seeders, factories, tests
- React + Vite + TypeScript + Tailwind CSS
- Versioned public API under `/api/v1`
- Session-protected admin API under `/admin/api`

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Default seeded admin:

```txt
Email: admin@futia.local
Password: password
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` before seeding in production.

## Admin

Open `/admin` and use the seeded admin account. The admin includes:

- Dashboard
- Sports, countries, leagues, seasons, teams, matches
- Providers
- API keys
- Sync jobs
- Request logs
- Settings
- Light/dark theme stored in `localStorage`
- Locale selector for `pt-BR`, `en`, `es`, `zh`

## Provider Keys

Create API keys in the API Keys page or via:

```http
POST /admin/api/provider-keys
```

Keys are stored with Laravel encrypted casts in `api_provider_keys.encrypted_key`. Responses only expose `key_hint` such as `****ABCD`.

### Football-Data.org

1. Create a key in the Football-Data.org dashboard.
2. In FutIA, open Providers and activate `Football-Data.org`.
3. Open API Keys and create a key for that provider.
4. Use sync config examples such as:

```json
{
  "competition_code": "BSA",
  "season": 2026
}
```

Football-Data.org requests use `X-Auth-Token`.

### API-Football / API-Sports

1. Create a key in the API-Sports dashboard.
2. Activate `API-Football`.
3. Create an API key in FutIA.
4. Use sync config examples such as:

```json
{
  "league_id": 71,
  "season": 2024,
  "timezone": "America/Sao_Paulo"
}
```

API-Football requests use `x-apisports-key`.

## Sync

Create a sync job in the admin, then run it from the UI or:

```bash
php artisan futia:sync:run
php artisan futia:sync:provider football-data --type=sync_leagues
```

Initial sync types:

- `sync_leagues`
- `sync_teams`
- `sync_matches`
- `sync_standings`
- `sync_match_statistics`

Football-Data.org and API-Football adapters now make real provider requests, normalize data, upsert records, create provider mappings, update job progress, and log requests. Other providers are seeded as planned placeholders.

### Manual Sync Config Examples

Football-Data matches:

```json
{
  "competition_code": "BSA",
  "season": 2026
}
```

API-Football teams:

```json
{
  "league_id": 71,
  "season": 2024
}
```

API-Football matches:

```json
{
  "league_id": 71,
  "season": 2024,
  "timezone": "America/Sao_Paulo"
}
```

API-Football standings:

```json
{
  "league_id": 71,
  "season": 2024
}
```

API-Football fixture statistics:

```json
{
  "fixture_id": 123456
}
```

Progress is calculated from `processed_items / total_items`. Jobs with zero remote items complete successfully with `total_items = 0`.

### Sync Job Detail

Open a sync job from the Sync Jobs page to inspect:

- job header, provider, status, timing, duration, and last error
- summary cards for totals, created, updated, failed, requests, and request errors
- formatted `config` and `result`
- `sync_job_items` with raw payload and item errors
- related external request logs

### Rerun and Cancel

`POST /admin/api/sync-jobs/{job}/rerun` creates a new pending job with the same provider, type, scope, and config. The original history is preserved through `parent_sync_job_id`.

`POST /admin/api/sync-jobs/{job}/cancel` marks pending jobs as cancelled. Running jobs record `cancel_requested_at`; long-running adapters check cancellation between processed items.

## Operations

Admin observability endpoints:

- `GET /admin/api/sync-jobs/{job}`
- `POST /admin/api/sync-jobs/{job}/rerun`
- `GET /admin/api/request-logs`
- `GET /admin/api/data-coverage`
- `GET /admin/api/providers/health`
- `GET /admin/api/schedules`
- `POST /admin/api/schedules`
- `POST /admin/api/schedules/{schedule}/run`
- `GET /admin/api/request-logs/export`
- `GET /admin/api/sync-jobs/export`
- `GET /admin/api/sync-jobs/{job}/items/export`

Request logs support filters for provider, success, status code, date range, sync job, and endpoint text. Provider health shows active keys, cooldown keys, requests today, errors today, 429 hits, average response time, and latest sync.

## Production Collection Controls

- **Batch processing:** sync adapters update progress item by item and check cancellation between pages/items.
- **Retry/backoff:** temporary failures such as timeouts, connection errors, 429, and 5xx responses are retried up to three times. In tests the waits are skipped; in production the backoff is immediate, 5 seconds, then 15 seconds.
- **Pagination:** API-Football reads `paging.current` and `paging.total`, and honors `page` and `max_pages` in sync config.
- **Incremental collection:** set `updated_since` in config and/or mark a job incremental. If the provider has no direct filter, records are still upserted safely and `last_synced_at` is refreshed.
- **Schedules:** `sync_schedules` can create recurring pending jobs using hourly, every 6 hours, daily, or weekly frequency. `php artisan futia:sync:run` creates due scheduled jobs before processing pending work.
- **Queue execution:** admin run actions and scheduler commands dispatch `RunSportsDataSyncJob` by default. Use `--sync` for direct foreground execution.
- **Locks:** duplicate pending/running jobs are blocked by provider/type/scope/config hash, and active executions use cache locks.
- **Stale recovery:** `php artisan futia:sync:recover-stale --minutes=60` marks old running jobs as failed, or `--requeue` moves them back to pending.
- **Alerts:** internal `system_alerts` are generated for failed jobs and provider/key health problems.
- **CSV exports:** request logs, sync jobs, and sync job items can be exported without exposing API keys.

Production commands:

```bash
php artisan futia:sync:run
php artisan futia:sync:run --sync
php artisan futia:sync:provider api-football
php artisan futia:sync:provider api-football --sync
php artisan futia:sync:recover-stale --minutes=60
php artisan queue:work database --sleep=3 --tries=1 --timeout=1200
php artisan queue:restart
```

## Scheduler

Add this cron on Ubuntu/aaPanel:

```bash
* * * * * cd /www/wwwroot/futia-data-hub && php artisan schedule:run >> /dev/null 2>&1
```

## Public API

- `GET /api/v1/sports`
- `GET /api/v1/countries`
- `GET /api/v1/leagues`
- `GET /api/v1/seasons`
- `GET /api/v1/teams`
- `GET /api/v1/matches`
- `GET /api/v1/matches/{id}`
- `GET /api/v1/standings`
- `GET /api/v1/stats/summary`
- `GET /api/v1/metadata`

Useful filters:

- `/api/v1/leagues?sport=football&country=BR&active=1`
- `/api/v1/teams?sport=football&country=BR&league_id=71`
- `/api/v1/matches?league_id=1&status=finished&date_from=2024-01-01&date_to=2024-12-31&provider=api-football`
- `/api/v1/standings?league_id=1&season_id=1`
- `/api/v1/leagues?updated_since=2026-05-01T00:00:00`
- `/api/v1/teams?updated_since=2026-05-01T00:00:00`
- `/api/v1/matches?updated_since=2026-05-01T00:00:00`

`/api/v1/metadata` returns API version, totals, last sync time, providers with data, and supported languages.

## Validation

```bash
composer install
npm install
php artisan migrate:fresh --seed
php artisan test
npm run typecheck
npm run build
```

More details:

- `docs/ARCHITECTURE.md`
- `docs/API_PROVIDERS.md`
- `docs/DEPLOY_AAPANEL_UBUNTU.md`
