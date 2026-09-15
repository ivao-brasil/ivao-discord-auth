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
}
