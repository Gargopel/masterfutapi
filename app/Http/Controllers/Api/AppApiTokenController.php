<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()
                ->apiTokens()
                ->latest()
                ->get()
                ->map(fn (UserApiToken $token) => $this->tokenPayload($token))
                ->all(),
            'limits' => $this->limitsPayload(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $activeTokens = $request->user()->apiTokens()->whereNull('revoked_at')->count();

        if ($activeTokens >= $this->maxActiveTokens($request->user())) {
            return response()->json([
                'message' => 'O plano free permite no maximo 3 API keys ativas por usuario.',
                'code' => 'api_key_limit_reached',
                'limits' => $this->limitsPayload(),
            ], 422);
        }

        [$token, $plainTextToken] = UserApiToken::issueFor($request->user(), $data['name']);

        return response()->json([
            'message' => 'API key criada com sucesso.',
            'api_key' => $this->tokenPayload($token, $plainTextToken),
            'limits' => $this->limitsPayload(),
        ], 201);
    }

    public function destroy(Request $request, UserApiToken $token): JsonResponse
    {
        if ($token->user_id !== $request->user()->id) {
            return response()->json(['message' => 'API key nao encontrada.'], 404);
        }

        $token->update(['revoked_at' => now()]);

        return response()->json([
            'message' => 'API key revogada com sucesso.',
            'api_key' => $this->tokenPayload($token->fresh()),
        ]);
    }

    private function tokenPayload(UserApiToken $token, ?string $plainTextToken = null): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'token' => $plainTextToken,
            'token_prefix' => $token->token_prefix,
            'last_used_at' => $token->last_used_at?->toISOString(),
            'revoked_at' => $token->revoked_at?->toISOString(),
            'created_at' => $token->created_at?->toISOString(),
        ];
    }

    private function limitsPayload(): array
    {
        return [
            'active_api_keys' => $this->maxActiveTokens(request()->user()),
            'requests_per_minute' => (int) (request()->user()?->plan?->requests_per_minute ?: Plan::default()->requests_per_minute),
        ];
    }

    private function maxActiveTokens(?User $user): int
    {
        return (int) ($user?->plan?->max_active_api_keys ?: Plan::default()->max_active_api_keys);
    }
}
