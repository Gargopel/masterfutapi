<?php

namespace App\Services\SportsData;

use App\Models\ApiProvider;
use App\Models\ApiProviderKey;
use App\Models\ApiRequestLog;

class ApiRequestLogger
{
    public function log(ApiProvider $provider, ?ApiProviderKey $key, array $data): ApiRequestLog
    {
        return ApiRequestLog::create([
            'api_provider_id' => $provider->id,
            'api_provider_key_id' => $key?->id,
            'sync_job_id' => $data['sync_job_id'] ?? null,
            'method' => $data['method'] ?? 'GET',
            'endpoint' => $data['endpoint'] ?? '/',
            'status_code' => $data['status_code'] ?? null,
            'success' => $data['success'] ?? false,
            'duration_ms' => $data['duration_ms'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'requested_at' => $data['requested_at'] ?? now(),
            'response_excerpt' => isset($data['response_excerpt']) ? str($data['response_excerpt'])->limit(500)->toString() : null,
        ]);
    }
}
