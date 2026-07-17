<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AppAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'api_key_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => false,
            'plan_id' => Plan::default()->id,
        ]);

        [$token, $plainTextToken] = UserApiToken::issueFor($user, $data['api_key_name'] ?? 'FutAI App');

        return response()->json([
            'message' => 'Conta criada com sucesso.',
            'user' => $this->userPayload($user),
            'api_key' => $this->tokenPayload($token, $plainTextToken),
            'limits' => $this->limitsPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'api_key_name' => ['nullable', 'string', 'max:120'],
            'issue_api_key' => ['sometimes', 'boolean'],
            'revoke_oldest' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais invalidas.'],
            ]);
        }

        if (($data['issue_api_key'] ?? true) === false) {
            return response()->json([
                'message' => 'Login realizado com sucesso.',
                'user' => $this->userPayload($user),
                'api_keys' => $this->activeTokensPayload($user),
                'limits' => $this->limitsPayload($user),
            ]);
        }

        $activeTokens = $user->apiTokens()->whereNull('revoked_at')->oldest()->get();
        $maxActiveTokens = $this->maxActiveTokens($user);

        if ($activeTokens->count() >= $maxActiveTokens) {
            if (! ($data['revoke_oldest'] ?? false)) {
                return response()->json([
                    'message' => 'O plano free permite no maximo 3 API keys ativas por usuario.',
                    'code' => 'api_key_limit_reached',
                    'user' => $this->userPayload($user),
                    'api_keys' => $this->activeTokensPayload($user),
                    'limits' => $this->limitsPayload($user),
                ], 422);
            }

            $activeTokens->first()?->update(['revoked_at' => now()]);
        }

        [$token, $plainTextToken] = UserApiToken::issueFor($user, $data['api_key_name'] ?? 'FutAI App');

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'user' => $this->userPayload($user),
            'api_key' => $this->tokenPayload($token, $plainTextToken),
            'limits' => $this->limitsPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $token = $request->attributes->get('user_api_token');

        return response()->json([
            'user' => $this->userPayload($request->user()),
            'current_api_key' => $token ? $this->tokenPayload($token) : null,
            'limits' => $this->limitsPayload($request->user()),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'created_at' => $user->created_at?->toISOString(),
            'plan' => $user->plan ? [
                'id' => $user->plan->id,
                'name' => $user->plan->name,
                'slug' => $user->plan->slug,
            ] : null,
        ];
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

    private function activeTokensPayload(User $user): array
    {
        return $user->apiTokens()
            ->whereNull('revoked_at')
            ->latest()
            ->get()
            ->map(fn (UserApiToken $token) => $this->tokenPayload($token))
            ->all();
    }

    private function limitsPayload(?User $user): array
    {
        return [
            'active_api_keys' => $this->maxActiveTokens($user),
            'requests_per_minute' => $this->requestsPerMinute($user),
        ];
    }

    private function maxActiveTokens(?User $user): int
    {
        return (int) ($user?->plan?->max_active_api_keys ?: Plan::default()->max_active_api_keys);
    }

    private function requestsPerMinute(?User $user): int
    {
        return (int) ($user?->plan?->requests_per_minute ?: Plan::default()->requests_per_minute);
    }
}
