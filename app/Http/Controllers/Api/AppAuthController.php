<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AppAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'api_key_name' => ['nullable', 'string', 'max:120'],
            'device_name' => ['required', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:60'],
            'app_version' => ['nullable', 'string', 'max:60'],
            'public_key' => $this->publicKeyRules(required: true),
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => false,
            'plan_id' => Plan::default()->id,
        ]);

        $device = $this->createDevice($user, $data);
        [$token, $plainTextToken] = UserApiToken::issueFor($user, $data['api_key_name'] ?? 'FutAI App', $device);

        return response()->json([
            'message' => 'Conta criada com sucesso.',
            'user' => $this->userPayload($user),
            'device' => $this->devicePayload($device),
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
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:60'],
            'app_version' => ['nullable', 'string', 'max:60'],
            'public_key' => $this->publicKeyRules(required: false),
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

        foreach (['device_name', 'public_key'] as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([
                    $field => ['Campo obrigatorio para emitir chave de dispositivo.'],
                ]);
            }
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

        $device = $this->createDevice($user, $data);
        [$token, $plainTextToken] = UserApiToken::issueFor($user, $data['api_key_name'] ?? 'FutAI App', $device);

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'user' => $this->userPayload($user),
            'device' => $this->devicePayload($device),
            'api_key' => $this->tokenPayload($token, $plainTextToken),
            'limits' => $this->limitsPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $token = $request->attributes->get('user_api_token');
        $device = $request->attributes->get('app_device');

        return response()->json([
            'user' => $this->userPayload($request->user()),
            'device' => $device ? $this->devicePayload($device) : null,
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
            'device' => $token->appDevice ? $this->devicePayload($token->appDevice) : null,
        ];
    }

    private function createDevice(User $user, array $data): AppDevice
    {
        return AppDevice::create([
            'user_id' => $user->id,
            'device_id' => (string) Str::uuid(),
            'name' => $data['device_name'],
            'platform' => $data['platform'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'public_key' => $data['public_key'],
        ]);
    }

    private function publicKeyRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:8000',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $decoded = base64_decode((string) $value, true);
                if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                    $fail('A chave publica do dispositivo deve ser Ed25519 em Base64.');
                }
            },
        ];
    }

    private function devicePayload(AppDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'name' => $device->name,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'last_used_at' => $device->last_used_at?->toISOString(),
            'revoked_at' => $device->revoked_at?->toISOString(),
            'created_at' => $device->created_at?->toISOString(),
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
