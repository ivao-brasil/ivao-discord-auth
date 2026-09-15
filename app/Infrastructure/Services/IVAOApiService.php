<?php


namespace App\Infrastructure\Services;


use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Support\Arr;

class IVAOApiService implements IVAOApiServiceContract
{
    private const SESSION_KEY = 'IVAO_USER';

    public function getUserData(): ?array
    {
        return request()->session()->get(self::SESSION_KEY);
    }

    public function storeUserData(array $user): void
    {
        // Cookie sessions are limited to 4KB, so keep only the fields the app uses
        request()->session()->put(self::SESSION_KEY, [
            'id' => $user['id'],
            'firstName' => $user['firstName'] ?? '',
            'lastName' => $user['lastName'] ?? '',
            'divisionId' => $user['divisionId'] ?? null,
            'rating' => [
                'networkRating' => ['id' => Arr::get($user, 'rating.networkRating.id')],
                'atcRating' => ['id' => Arr::get($user, 'rating.atcRating.id')],
                'pilotRating' => ['id' => Arr::get($user, 'rating.pilotRating.id')],
            ],
            'hours' => array_map(
                fn ($hours) => Arr::only($hours, ['type', 'hours']),
                $user['hours'] ?? []
            ),
            'userStaffPositions' => array_map(
                fn ($position) => Arr::only($position, ['id', 'connectAs', 'onTrial']),
                $user['userStaffPositions'] ?? []
            ),
            'gcas' => array_map(fn ($gca) => Arr::only($gca, ['id']), $user['gcas'] ?? []),
            'ownedVirtualAirlines' => array_map(fn ($airline) => Arr::only($airline, ['id']), $user['ownedVirtualAirlines'] ?? []),
        ]);
    }
}
