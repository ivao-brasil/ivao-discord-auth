<?php


namespace App\Domain\Contracts;


use App\Domain\Entities\Consentment;
use Illuminate\Support\Collection;

interface ConsentmentServiceContract
{
    public function create(Consentment $consentment);
    public function hasAnotherLinkedAccount($userVid, $discordId);
    /** @return  Collection */
    public function getAnotherLinkedAccounts($userVid, $discordId);
    public function remove($userVid);
    /** @return  Collection */
    public function getActiveAccounts($userVid);
}
