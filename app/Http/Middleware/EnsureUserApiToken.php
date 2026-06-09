<?php

namespace App\Http\Middleware;

use App\Models\UserApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserApiToken
{
    private const REQUESTS_PER_MINUTE = 10;

    public function handle(Request $request, Closure $next): Response
    {
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

        $rateLimitKey = 'masterfut:user:'.$token->user_id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::REQUESTS_PER_MINUTE)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Limite de 10 requisicoes por minuto atingido.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => self::REQUESTS_PER_MINUTE,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('user_api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) self::REQUESTS_PER_MINUTE);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($rateLimitKey, self::REQUESTS_PER_MINUTE));

        return $response;
    }
}
