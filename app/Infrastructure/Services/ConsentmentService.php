<?php


namespace App\Infrastructure\Services;


use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Entities\Consentment;

class ConsentmentService implements ConsentmentServiceContract
{

    public function create(Consentment $consentment)
    {
        $consentment = new ConsentmentModel($consentment->toRaw());
        $consentment->save();
    }

    public function remove($userVid)
    {
        // TODO: Implement remove() method.
    }

    public function hasAnotherLinkedAccount($userVid, $discordId)
    {
        $data = $this->getAnotherLinkedAccounts($userVid, $discordId);
        return sizeof($data) != 0;
    }

    public function getAnotherLinkedAccounts($userVid, $discordId)
    {
        return ConsentmentModel::where('uservid', $userVid)->where('discordId', '!=', $discordId)->get();
    }
}