<?php


namespace App\Domain\Entities;


use Illuminate\Support\Carbon;

class Consentment
{
    private $userVid;
    private $discordId;
    private $nickName;
    private $roles;

    function __construct($data)
    {
        $this->userVid = $data['userVid'];
        $this->discordId = $data['discordId'];
        $this->nickName = $data['nickName'];
        $this->roles = $data['roles'];
    }

    function toRaw() {
        return [
            'userVid' => $this->userVid,
            'discordId' => $this->discordId,
            'nickName' => $this->nickName,
            'roles' => $this->roles
        ];
    }
}
