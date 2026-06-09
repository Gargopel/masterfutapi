# Architecture

## Backend

The backend is organized around the provider and sync pipeline.

- `app/Services/SportsData/Contracts/SportsDataProviderInterface.php` defines the adapter contract.
- `app/Services/SportsData/Providers` contains Football-Data.org, API-Football, and placeholder providers.
- `ProviderRegistry` resolves a seeded provider slug to the correct adapter.
- `ProviderKeyResolver` selects an active key that is not in cooldown.
- `ProviderRateLimiter` enforces per-minute and per-day limits.
- `ApiRequestLogger` records external calls.
- `SportsDataSyncService` runs sync jobs through adapters.
- `SyncProgressService` calculates job and overall progress.

## Real Provider Flow

1. Admin creates an encrypted provider key.
2. Admin creates a `sync_jobs` row with a sync type and JSON config.
3. `SportsDataSyncService` checks that the provider is active.
4. The adapter resolves an active key, checks rate limits, sends the HTTP request, and logs it.
5. The adapter normalizes remote records through `SportsDataNormalizer`.
6. Every external entity writes a `provider_mappings` row.
7. The job updates `total_items`, `processed_items`, `created_items`, `updated_items`, `failed_items`, `progress_percent`, `result`, and timestamps.

## Operational Observability

Sync jobs are inspectable through `/admin/api/sync-jobs/{job}`. The response includes the job, summary metrics, paginated `sync_job_items`, and request logs linked by `api_request_logs.sync_job_id`.

Request logs can be filtered by provider, success, status code, date range, sync job, and endpoint. Sensitive token-like query parameters are masked before being logged.

Reruns create a new `sync_jobs` record with `parent_sync_job_id`; old jobs are never overwritten. Cancellation records `cancel_requested_at` for running work and sets cancelled state for pending work.

`/admin/api/data-coverage` aggregates data by league and season. `/admin/api/providers/health` aggregates request volume, errors, cooldown keys, 429s, and response times by provider.

## Production Sync Execution

The sync layer now supports production-oriented controls:

- Adapters process remote records item by item and update `sync_job_items`.
- Cancellation is checked before paginated requests and during item loops through `SyncProgressService::shouldCancel`.
- Temporary request failures are retried up to three times with bounded backoff.
- API-Football pagination follows `paging.current` and `paging.total`; `max_pages` prevents runaway collection.
- Incremental syncs use `updated_since` in config and mark result metadata with records considered/changed.
- `SyncScheduleService` creates due jobs from `sync_schedules` before the scheduler command processes pending jobs.
- `SystemAlertService` creates internal alerts for failed jobs and provider/key configuration issues.
- `RunSportsDataSyncJob` executes one sync job asynchronously with `timeout = 1200` and `tries = 1`.
- `SyncLockService` prevents duplicate pending/running jobs and duplicate active execution for the same provider/type/scope/config hash.
- `futia:sync:run` dispatches pending jobs by default; `--sync` executes inline for development and maintenance.
- `futia:sync:recover-stale` recovers jobs stuck in `running` and creates alerts.

CSV exports are streamed through `CsvExporter` and intentionally exclude encrypted provider keys.

## Queue Health

`/admin/api/system/queue-health` reports queue connection, pending queue jobs, failed jobs, last processed sync job, running jobs, stale running jobs, and an operational recommendation.

## Error Handling

- No active key: `No active API key configured for provider.`
- Rate limit exceeded: `Provider rate limit exceeded.`
- 429: key is placed in cooldown.
- 401/403: key `last_error` is updated.
- cURL error 60: SSL/CA certificate message is logged and written to the sync job.

To investigate a failed sync:

1. Open the sync job detail page.
2. Check `last_error` and failed `sync_job_items`.
3. Review related request logs for HTTP status, duration, response excerpt, and SSL/cURL errors.
4. Check provider health for 429/rate-limit patterns or cooldown keys.

## Database

Core sports tables:

- `sports`
- `countries`
- `leagues`
- `seasons`
- `teams`
- `matches`
- `match_events`
- `match_statistics`
- `standings`
- `players`
- `provider_mappings`

Provider and sync tables:

- `api_providers`
- `api_provider_keys`
- `api_request_logs`
- `sync_jobs`
- `sync_job_items`

## Admin

The admin is a React SPA served by Laravel. It uses session authentication and calls `/admin/api/*`.

Theme and locale are saved in browser `localStorage`.

## Public API

Public API routes live under `/api/v1`. They intentionally do not expose provider API keys or internal encrypted values.

Filters are available for leagues, teams, matches, and standings so FutIA Desktop can request only the relevant local data.

## Future API Clients

The current schema keeps external provider credentials separate from future customer API keys. A later release can add client apps, client keys, quotas, and billing without changing provider storage.
