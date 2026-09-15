<?php


namespace App\Domain\Contracts;


use App\ConsentmentModel;
use App\Domain\Entities\Consentment;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface ConsentmentServiceContract
{
    public function create(Consentment $consentment);
    public function hasAnotherLinkedAccount($userVid, $discordId);
    /** @return  Collection */
    public function getAnotherLinkedAccounts($userVid, $discordId);
    public function remove($userVid);
    /** @return  Collection */
    public function getActiveAccounts($userVid);

    public function findActiveByDiscordId(string $discordId): ?ConsentmentModel;

    /** @return LazyCollection<int, ConsentmentModel> */
    public function allActive(): LazyCollection;

    public function deactivate(ConsentmentModel $account): void;

    public function updateSynced(ConsentmentModel $account, string $nickname, string $roles): void;
}
