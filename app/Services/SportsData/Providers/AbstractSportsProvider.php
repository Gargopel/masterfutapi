<?php

namespace App\Services\SportsData\Providers;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\SyncJob;
use App\Models\SyncJobItem;
use App\Services\SportsData\ApiRequestLogger;
use App\Services\SportsData\Contracts\SportsDataProviderInterface;
use App\Services\SportsData\ProviderKeyResolver;
use App\Services\SportsData\ProviderRateLimiter;
use App\Services\SportsData\SystemAlertService;
use App\Services\SportsData\SyncProgressService;
use App\Support\SportsData\ProviderTestResult;
use App\Support\SportsData\SyncResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

abstract class AbstractSportsProvider implements SportsDataProviderInterface
{
    protected ?SyncJob $activeSyncJob = null;

    public function __construct(protected ApiProvider $provider) {}

    public function testConnection(): ProviderTestResult
    {
        if (! $this->provider->is_active) {
            return new ProviderTestResult(false, 'Provider inactive.');
        }

        return new ProviderTestResult(true, 'Provider configured.');
    }

    public function syncLeagues(SyncJob $job): SyncResult { return $this->completeNoop($job, 'sync_leagues'); }
    public function syncTeams(SyncJob $job): SyncResult { return $this->completeNoop($job, 'sync_teams'); }
    public function syncMatches(SyncJob $job): SyncResult { return $this->completeNoop($job, 'sync_matches'); }
    public function syncStandings(SyncJob $job): SyncResult { return $this->completeNoop($job, 'sync_standings'); }
    public function syncMatchStatistics(SyncJob $job): SyncResult { return $this->completeNoop($job, 'sync_match_statistics'); }

    protected function completeNoop(SyncJob $job, string $type): SyncResult
    {
        $job->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'total_items' => $job->total_items ?? 0,
            'processed_items' => $job->processed_items ?? 0,
            'finished_at' => now(),
            'result' => ['message' => 'Adapter ready; no remote import was requested for this initial run.', 'type' => $type],
        ]);

        return new SyncResult(true, 'Sync completed.', $job->result ?? []);
    }

    protected function begin(SyncJob $job): void
    {
        $this->activeSyncJob = $job;
        app(SyncProgressService::class)->start($job);
    }

    protected function fail(SyncJob $job, string $message): SyncResult
    {
        app(SyncProgressService::class)->fail($job, $message);

        return new SyncResult(false, $message);
    }

    protected function complete(SyncJob $job, array $result = []): SyncResult
    {
        $result += [
            'incremental' => (bool) $job->is_incremental,
            'updated_since' => data_get($job->config ?? [], 'updated_since'),
            'records_considered' => $job->processed_items ?? 0,
            'records_changed' => ($job->created_items ?? 0) + ($job->updated_items ?? 0),
        ];
        app(SyncProgressService::class)->complete($job, $result);

        return new SyncResult(true, 'Sync completed.', $result);
    }

    protected function incrementProgress(SyncJob $job, string $action = 'updated', ?string $entityType = null, ?string $externalId = null, ?int $entityId = null, ?array $rawPayload = null): void
    {
        $job->refresh();
        if (app(SyncProgressService::class)->shouldCancel($job)) {
            throw new RuntimeException('Sync job cancellation requested.');
        }

        app(SyncProgressService::class)->advance($job, $action);

        if ($entityType || $externalId) {
            SyncJobItem::create([
                'sync_job_id' => $job->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'external_id' => $externalId,
                'status' => 'completed',
                'action' => $action,
                'raw_payload' => $rawPayload,
            ]);
        }
    }

    protected function recordItemFailure(SyncJob $job, string $message, ?string $entityType = null, ?string $externalId = null, ?array $rawPayload = null): void
    {
        $job->refresh();
        app(SyncProgressService::class)->markItemFailed($job);

        SyncJobItem::create([
            'sync_job_id' => $job->id,
            'entity_type' => $entityType,
            'external_id' => $externalId,
            'status' => 'failed',
            'error_message' => $message,
            'raw_payload' => $rawPayload,
        ]);
    }

    protected function request(string $method, string $endpoint, array $query = [], array $headers = []): array
    {
        if (! $this->provider->is_active) {
            throw new RuntimeException('Provider inactive.');
        }

        $limiter = app(ProviderRateLimiter::class);
        $url = rtrim((string) $this->provider->base_url, '/').'/'.ltrim($endpoint, '/');
        $logger = app(ApiRequestLogger::class);

        $attempts = 0;
        $lastMessage = null;
        while ($attempts < 3) {
            $attempts++;
            $key = app(ProviderKeyResolver::class)->resolve($this->provider);
            if (! $key) {
                throw new RuntimeException($lastMessage ?: 'No active API key configured for provider.');
            }
            if (! $limiter->canRequest($this->provider, $key)) {
                $lastMessage = 'Provider rate limit exceeded.';
                break;
            }

            $started = microtime(true);
            try {
                $response = $this->http()->withHeaders($this->headersForKey($headers, $key))->send(strtoupper($method), $url, ['query' => $query]);
                $duration = (int) round((microtime(true) - $started) * 1000);
                $logger->log($this->provider, $key, [
                    'method' => strtoupper($method),
                    'endpoint' => $this->logEndpoint($endpoint, $query),
                    'status_code' => $response->status(),
                    'success' => $response->successful(),
                    'duration_ms' => $duration,
                    'sync_job_id' => $this->activeSyncJob?->id,
                    'error_message' => $response->successful() ? null : $this->responseError($response),
                    'response_excerpt' => $response->body(),
                ]);
                $limiter->markUsed($key);

                if ($response->status() === 429) {
                    $limiter->cooldown($key, 'Rate limit reached.');
                    $lastMessage = 'Provider rate limit reached; API key placed in cooldown.';
                    $this->backoff($attempts);
                    continue;
                }
                if (in_array($response->status(), [401, 403], true)) {
                    $key->update(['last_error' => 'Authentication failed. Check provider API key.']);
                    throw new RuntimeException('Authentication failed. Check provider API key.');
                }
                if ($response->successful()) {
                    return $response->json() ?? [];
                }
                $lastMessage = $this->responseError($response);
                if (! in_array($response->status(), [500, 502, 503, 504], true)) {
                    throw new RuntimeException($lastMessage);
                }
            } catch (ConnectionException $e) {
                $lastMessage = $this->friendlyConnectionMessage($e);
                $logger->log($this->provider, $key, [
                    'method' => strtoupper($method),
                    'endpoint' => $this->logEndpoint($endpoint, $query),
                    'success' => false,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'sync_job_id' => $this->activeSyncJob?->id,
                    'error_message' => $lastMessage,
                ]);
            }
            $this->backoff($attempts);
        }

        throw new RuntimeException($lastMessage ?: 'Provider request failed.');
    }

    protected function withJobFailure(SyncJob $job, callable $callback): SyncResult
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if ($e->getMessage() === 'Sync job cancellation requested.') {
                app(SyncProgressService::class)->cancel($job, ['processed_until_cancel' => $job->fresh()->processed_items, 'cancelled_by_request' => true]);
                return new SyncResult(false, 'Sync job cancellation requested.');
            }

            $result = $this->fail($job, $this->friendlyConnectionMessage($e));
            app(SystemAlertService::class)->syncFailed($job->fresh());
            return $result;
        }
    }

    protected function updateTotal(SyncJob $job, int $total): void
    {
        $job->update(['total_items' => $total, 'progress_percent' => $total === 0 ? 100 : 0]);
    }

    protected function processBatch(SyncJob $job, iterable $items, callable $callback, int $batchSize = 50): void
    {
        foreach (collect($items)->chunk($batchSize) as $chunk) {
            if (app(SyncProgressService::class)->shouldCancel($job)) {
                throw new RuntimeException('Sync job cancellation requested.');
            }
            foreach ($chunk as $item) {
                try {
                    $callback($item);
                } catch (Throwable $e) {
                    $this->recordItemFailure($job, $e->getMessage(), null, null, is_array($item) ? $item : null);
                }
            }
        }
    }

    protected function actionFor(object $model): string
    {
        return $model->wasRecentlyCreated ? 'created' : 'updated';
    }

    protected function configValue(SyncJob $job, string $key): mixed
    {
        return data_get($job->config ?? [], $key);
    }

    protected function http()
    {
        return Http::timeout(20)->acceptJson();
    }

    private function logEndpoint(string $endpoint, array $query): string
    {
        foreach (['api_key', 'apikey', 'key', 'token', 'access_token', 'x-apisports-key', 'X-Auth-Token'] as $sensitive) {
            if (array_key_exists($sensitive, $query)) {
                $query[$sensitive] = '***';
            }
        }

        return $endpoint.($query ? '?'.http_build_query($query) : '');
    }

    private function responseError(Response $response): string
    {
        return str($response->body())->limit(300)->prepend("HTTP {$response->status()}: ")->toString();
    }

    protected function friendlyConnectionMessage(Throwable $e): string
    {
        if (str_contains($e->getMessage(), 'cURL error 60')) {
            return 'SSL certificate validation failed. Check CA certificates on the server.';
        }

        if (str_contains($e->getMessage(), 'cURL error 35')) {
            return 'SSL/TLS handshake failed. Check server OpenSSL/cURL, CA certificates, outbound firewall, and IPv6 connectivity.';
        }

        return $e->getMessage();
    }

    private function backoff(int $attempt): void
    {
        if (app()->environment('testing')) {
            return;
        }
        sleep(match ($attempt) {
            1 => 0,
            2 => 5,
            default => 15,
        });
    }

    private function headersForKey(array $headers, ApiProviderKey $key): array
    {
        if (array_key_exists('X-Auth-Token', $headers)) {
            $headers['X-Auth-Token'] = $key->encrypted_key;
        }
        if (array_key_exists('x-apisports-key', $headers)) {
            $headers['x-apisports-key'] = $key->encrypted_key;
        }

        return $headers;
    }
}
