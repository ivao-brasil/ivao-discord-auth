<?php

namespace App\Infrastructure\Services;

use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Infrastructure\Socialite\IVAOProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IVAOUserDirectory implements IVAOUserDirectoryContract
{
    private const USERS_URL = 'https://api.ivao.aero/v2/users/';
    private const TOKEN_CACHE_KEY = 'ivao.api.token';

    public function find(string $vid): ?array
    {
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->get(self::USERS_URL.rawurlencode($vid));

        if ($response->notFound()) {
            return null;
        }

        return $response->throw()->json();
    }

    private function accessToken(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $token;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post(IVAOProvider::TOKEN_URL, [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.ivao.client_id'),
                'client_secret' => config('services.ivao.client_secret'),
            ])
            ->throw();

        $token = $response->json('access_token');
        $lifetime = max(60, (int) $response->json('expires_in', 3600) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $lifetime);

        return $token;
    }
}
