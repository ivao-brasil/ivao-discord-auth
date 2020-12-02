<?php


namespace App\Domain\Contracts;


use App\Domain\Entities\Consentment;

interface ConsentmentServiceContract
{
    public function create(Consentment $consentment);
    public function hasLinkedAccount($userVid);
    public function remove($userVid);
}
