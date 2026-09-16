<?php

namespace App\Domain\Contracts;

interface IVAOUserDirectoryContract
{
    /**
     * The IVAO API v2 user for the VID, or null when the account does not exist.
     *
     * @throws \Illuminate\Http\Client\RequestException when the API cannot be reached
     */
    public function find(string $vid): ?array;

    /**
     * Several users at once, as a map of VID to user or null.
     * VIDs the API did not answer for are left out, to be fetched one by one.
     *
     * @param  string[]  $vids
     * @return array<string, array|null>
     */
    public function findMany(array $vids): array;

    /**
     * Every staff position of the network, as a map of VID to positions. IVAO hides the
     * positions of private profiles on the user endpoint but lists them here.
     *
     * @return array<string, array<int, array{id: string, connectAs: string, onTrial: bool}>>
     *
     * @throws \Illuminate\Http\Client\RequestException when the API cannot be reached
     */
    public function staffPositions(): array;
}
