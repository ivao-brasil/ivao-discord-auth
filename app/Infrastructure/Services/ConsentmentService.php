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

    public function hasLinkedAccount($userVid)
    {
        // TODO: Implement hasLinkedAccount() method.
    }

    public function remove($userVid)
    {
        // TODO: Implement remove() method.
    }
}
