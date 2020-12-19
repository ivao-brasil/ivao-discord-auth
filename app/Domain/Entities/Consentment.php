<?php


namespace App\Domain\Entities;


use Illuminate\Support\Carbon;

class Consentment
{
    private $userVid;
    private $discordId;
    private $nickName;
    private $roles;
    private $division;
    private $status;

    function __construct($data)
    {
        $this->userVid = $data['userVid'];
        $this->discordId = $data['discordId'];
        $this->nickName = $data['nickName'];
        $this->roles = $data['roles'];
        $this->division = $data['division'];
        $this->status = $data['status'];
    }

    function toRaw() {
        return [
            'userVid' => $this->userVid,
            'discordId' => $this->discordId,
            'nickName' => $this->nickName,
            'roles' => $this->roles,
            'division' => $this->division,
            'status' => $this->status
        ];
    }
}
