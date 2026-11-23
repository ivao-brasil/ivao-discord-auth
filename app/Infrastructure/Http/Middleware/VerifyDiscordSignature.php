<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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
            abort(401, 'Invalid request signature');
        }

        return $next($request);
    }

    private function isHex(string $value, int $bytes): bool
    {
        return strlen($value) === $bytes * 2 && ctype_xdigit($value);
    }
}
