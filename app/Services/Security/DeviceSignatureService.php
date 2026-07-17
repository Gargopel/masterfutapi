<?php

namespace App\Services\Security;

use App\Models\AppDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceSignatureService
{
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    public function verify(Request $request, AppDevice $device): array
    {
        $deviceId = $request->header('X-FutAI-Device-Id');
        $timestamp = $request->header('X-FutAI-Timestamp');
        $nonce = $request->header('X-FutAI-Nonce');
        $signature = $request->header('X-FutAI-Signature');

        if (! $deviceId || ! $timestamp || ! $nonce || ! $signature) {
            return [false, 'Assinatura do FutAI obrigatoria.'];
        }

        if ($deviceId !== $device->device_id) {
            return [false, 'Dispositivo nao corresponde a chave informada.'];
        }

        if (! ctype_digit((string) $timestamp) || abs(now()->timestamp - (int) $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            return [false, 'Timestamp da assinatura expirado ou invalido.'];
        }

        $nonceKey = "futai:device_nonce:{$device->device_id}:{$nonce}";
        if (! Cache::add($nonceKey, true, self::MAX_CLOCK_SKEW_SECONDS)) {
            return [false, 'Nonce da assinatura ja utilizado.'];
        }

        $decodedSignature = base64_decode((string) $signature, true);
        $publicKey = base64_decode($device->public_key, true);
        if ($decodedSignature === false) {
            return [false, 'Assinatura em formato invalido.'];
        }

        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return [false, 'Chave publica do dispositivo em formato invalido.'];
        }

        if (! sodium_crypto_sign_verify_detached($decodedSignature, $this->canonicalPayload($request, (string) $timestamp, (string) $nonce, $device->device_id), $publicKey)) {
            return [false, 'Assinatura do FutAI invalida.'];
        }

        return [true, null];
    }

    public function canonicalPayload(Request $request, string $timestamp, string $nonce, string $deviceId): string
    {
        $queryString = $request->server->get('QUERY_STRING');

        return implode("\n", [
            strtoupper($request->method()),
            '/'.$request->path().($queryString ? '?'.$queryString : ''),
            hash('sha256', $request->getContent() ?: ''),
            $timestamp,
            $nonce,
            $deviceId,
        ]);
    }
}
