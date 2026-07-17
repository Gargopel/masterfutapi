<?php

namespace App\Http\Middleware;

use App\Models\UserApiToken;
use App\Models\UserApiRequestLog;
use App\Services\Security\DeviceSignatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserApiToken
{
    public const DEFAULT_REQUESTS_PER_MINUTE = 10;

    public function __construct(private readonly DeviceSignatureService $deviceSignature) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $plainTextToken = $request->bearerToken() ?: $request->header('X-API-Key');

        if (! is_string($plainTextToken) || trim($plainTextToken) === '') {
            return response()->json([
                'message' => 'API key obrigatoria. Envie Authorization: Bearer {token} ou X-API-Key.',
            ], 401);
        }

        $token = UserApiToken::query()
            ->where('token_hash', UserApiToken::hashToken($plainTextToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $token) {
            return response()->json([
                'message' => 'API key invalida ou revogada.',
            ], 401);
        }

        $device = $token->appDevice;
        if (! $device || ! $device->isActive()) {
            return response()->json([
                'message' => 'Dispositivo FutAI obrigatorio para acessar a API.',
                'code' => 'device_required',
            ], 401);
        }

        [$validSignature, $signatureError] = $this->deviceSignature->verify($request, $device);
        if (! $validSignature) {
            return response()->json([
                'message' => $signatureError,
                'code' => 'invalid_device_signature',
            ], 401);
        }

        $requestsPerMinute = (int) ($token->user?->plan?->requests_per_minute ?: self::DEFAULT_REQUESTS_PER_MINUTE);
        $rateLimitKey = 'masterfut:user:'.$token->user_id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $requestsPerMinute)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);
            $this->logRequest($request, $token, 429, $startedAt);

            return response()->json([
                'message' => 'Limite de 10 requisicoes por minuto atingido.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $requestsPerMinute,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);

        $token->forceFill(['last_used_at' => now()])->save();
        $device->forceFill([
            'last_used_at' => now(),
            'app_version' => $request->header('X-FutAI-App-Version') ?: $device->app_version,
        ])->save();
        $request->attributes->set('user_api_token', $token);
        $request->attributes->set('app_device', $device);
        $request->setUserResolver(fn () => $token->user);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $requestsPerMinute);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($rateLimitKey, $requestsPerMinute));
        $this->logRequest($request, $token, $response->getStatusCode(), $startedAt);

        return $response;
    }

    private function logRequest(Request $request, UserApiToken $token, int $statusCode, float $startedAt): void
    {
        UserApiRequestLog::create([
            'user_id' => $token->user_id,
            'user_api_token_id' => $token->id,
            'method' => $request->method(),
            'endpoint' => '/'.$request->path(),
            'status_code' => $statusCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'requested_at' => now(),
        ]);
    }
}
