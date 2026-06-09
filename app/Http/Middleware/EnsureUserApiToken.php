<?php

namespace App\Http\Middleware;

use App\Models\UserApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserApiToken
{
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

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('user_api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
