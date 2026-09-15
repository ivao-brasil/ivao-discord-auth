<?php

namespace Tests\Support;

use App\Domain\Entities\Member;
use App\Infrastructure\Services\RolesService;

trait IvaoFixtures
{
    /**
     * An IVAO API v2 user; hours are in seconds, as returned by the API.
     */
    protected function ivaoUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 123456,
            'firstName' => 'Fulano da Silva',
            'lastName' => 'Souza',
            'divisionId' => 'BR',
            'rating' => [
                'networkRating' => ['id' => Member::STATUS_ACTIVE],
                'atcRating' => ['id' => 5],
                'pilotRating' => ['id' => 4],
            ],
            'hours' => [['type' => 'pilot', 'hours' => 36000], ['type' => 'atc', 'hours' => 0]],
            'userStaffPositions' => [['id' => 'BR-WM', 'connectAs' => 'BR-WM', 'onTrial' => false]],
            'gcas' => [],
            'ownedVirtualAirlines' => [],
        ], $overrides);
    }

    protected function saveRoleRules(array $rules): void
    {
        app(RolesService::class)->saveAllRoles($rules);
    }
}
