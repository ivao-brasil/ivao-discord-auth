<?php


namespace App\Domain\Contracts;


interface IVAOApiServiceContract {
    /**
     * The authenticated IVAO user (IVAO API v2 /users/me format), or null when not logged in.
     */
    public function getUserData(): ?array;

    /**
     * Keep the IVAO user returned by the SSO for the current session.
     */
    public function storeUserData(array $user): void;
}
