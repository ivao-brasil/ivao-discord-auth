<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Rejects requests to the interactions endpoint that were not signed by Discord.
 *
 * @see https://discord.com/developers/docs/interactions/overview#setting-up-an-endpoint-validating-security-request-headers
 */
class VerifyDiscordSignature
{
    public function handle(Request $request, Closure $next)
    {
        $signature = (string) $request->header('X-Signature-Ed25519');
        $timestamp = (string) $request->header('X-Signature-Timestamp');
        $publicKey = (string) config('services.discord.public_key');

        if (! $this->isHex($signature, SODIUM_CRYPTO_SIGN_BYTES)
            || ! $this->isHex($publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)
            || $timestamp === ''
            || ! sodium_crypto_sign_verify_detached(hex2bin($signature), $timestamp.$request->getContent(), hex2bin($publicKey))) {
            $this->trace('rejected', $request);
            abort(401, 'Invalid request signature');
        }

        $this->trace('received', $request);

        return $next($request);
    }

    // Temporário: rastreia o /sync enquanto o timeout é investigado
    public function terminate(Request $request, $response): void
    {
        $this->trace('answered', $request, ['status' => $response->getStatusCode()]);
    }

    private function trace(string $step, Request $request, array $context = []): void
    {
        Log::channel('single')->notice("discord.interaction.{$step}", $context + [
            'type' => $request->input('type'),
            'command' => $request->input('data.name'),
            'ms' => (int) round((microtime(true) - request()->server('REQUEST_TIME_FLOAT')) * 1000),
        ]);
    }

    private function isHex(string $value, int $bytes): bool
    {
        return strlen($value) === $bytes * 2 && ctype_xdigit($value);
    }
}
