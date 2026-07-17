<?php

namespace Tests;

use App\Models\AppDevice;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function apiHeaders(?User $user = null, string $method = 'GET', string $path = '/api/v1/metadata', string $body = ''): array
    {
        $user ??= User::factory()->create(['is_admin' => false]);
        [$privateKey, $publicKey] = $this->deviceKeyPair();
        $device = AppDevice::create([
            'user_id' => $user->id,
            'device_id' => (string) Str::uuid(),
            'name' => 'Test device',
            'platform' => 'phpunit',
            'app_version' => 'test',
            'public_key' => $publicKey,
        ]);
        [, $plainTextToken] = UserApiToken::issueFor($user, 'Test key', $device);

        return $this->signedApiHeaders($plainTextToken, $device, $privateKey, $method, $path, $body);
    }

    protected function issueSignedDeviceToken(User $user, string $name = 'Test key'): array
    {
        [$privateKey, $publicKey] = $this->deviceKeyPair();
        $device = AppDevice::create([
            'user_id' => $user->id,
            'device_id' => (string) Str::uuid(),
            'name' => 'Test device',
            'platform' => 'phpunit',
            'app_version' => 'test',
            'public_key' => $publicKey,
        ]);
        [$token, $plainTextToken] = UserApiToken::issueFor($user, $name, $device);

        return [$token, $plainTextToken, $device, $privateKey, $publicKey];
    }

    protected function signedApiHeaders(string $plainTextToken, AppDevice $device, string $privateKey, string $method, string $path, string $body = ''): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $bodyForSignature = $body === '' && in_array(strtoupper($method), ['GET', 'DELETE'], true) ? '[]' : $body;
        $payload = implode("\n", [
            strtoupper($method),
            $path,
            hash('sha256', $bodyForSignature),
            $timestamp,
            $nonce,
            $device->device_id,
        ]);
        $signature = sodium_crypto_sign_detached($payload, base64_decode($privateKey));

        return [
            'Authorization' => 'Bearer '.$plainTextToken,
            'X-FutAI-Device-Id' => $device->device_id,
            'X-FutAI-Timestamp' => $timestamp,
            'X-FutAI-Nonce' => $nonce,
            'X-FutAI-Signature' => base64_encode($signature),
            'X-FutAI-App-Version' => 'test',
        ];
    }

    protected function deviceKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();
        $privateKey = base64_encode(sodium_crypto_sign_secretkey($keyPair));
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keyPair));

        return [$privateKey, $publicKey];
    }
}
