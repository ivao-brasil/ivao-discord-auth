<?php

namespace App\Infrastructure\Services;

use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Infrastructure\Socialite\IVAOProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IVAOUserDirectory implements IVAOUserDirectoryContract
{
    private const USERS_URL = 'https://api.ivao.aero/v2/users/';

    private const STAFF_URL = 'https://api.ivao.aero/v2/userStaffPositions';

    private const STAFF_CACHE_KEY = 'ivao.staff.positions';
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

    public function findMany(array $vids): array
    {
        $token = $this->accessToken();

        $responses = Http::pool(fn ($pool) => array_map(
            fn (string $vid) => $pool->as($vid)
                ->withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get(self::USERS_URL.rawurlencode($vid)),
            array_values($vids)
        ));

        $users = [];

        foreach ($responses as $vid => $response) {
            if ($response instanceof \Throwable) {
                continue;
            }

            if ($response->successful()) {
                $users[(string) $vid] = $response->json();
            } elseif ($response->notFound()) {
                $users[(string) $vid] = null;
            }
            // Anything else is left for the single request, which reports the failure
        }

        return $users;
    }

    /**
     * Every staff position of the network, as a map of VID to the positions of that member.
     *
     * IVAO hides the positions of private profiles on the user endpoint but lists them here,
     * so this is what tells the sync who is staff.
     *
     * @return array<string, array<int, array{id: string, connectAs: string, onTrial: bool}>>
     */
    public function staffPositions(): array
    {
        return Cache::remember(self::STAFF_CACHE_KEY, now()->addHour(), function () {
            $token = $this->accessToken();
            $positions = [];
            $page = 1;

            do {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(20)
                    ->get(self::STAFF_URL, ['page' => $page, 'perPage' => 100])
                    ->throw()
                    ->json();

                foreach ($response['items'] ?? [] as $item) {
                    if (empty($item['userId']) || empty($item['id'])) {
                        continue;
                    }

                    $positions[(string) $item['userId']][] = [
                        'id' => (string) $item['id'],
                        'connectAs' => (string) ($item['connectAs'] ?? $item['id']),
                        'onTrial' => (bool) ($item['onTrial'] ?? false),
                    ];
                }

                $pages = (int) ($response['pages'] ?? 1);
            } while ($page++ < $pages);

            return $positions;
        });
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
