<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\IVAOApiServiceContract;
use Illuminate\Support\Collection;

class Member
{
    private $vid;
    private $firstName;
    private $division;

    /** @var Collection  */

    private $staff;
    private $discordId;
    private $discordAccessToken;

    /** @var Collection */

    private $roles;

    /**
     * @return Collection
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @return mixed
     */
    public function getDiscordAccessToken()
    {
        return $this->discordAccessToken;
    }

    /**
     * @return mixed
     */
    public function getDiscordId()
    {
        return $this->discordId;
    }


    /**
     * Member constructor.
     * @param $IVAOTOKEN
     */
    private function __construct($userData)
    {
        $this->vid = $userData['vid'];
        $this->firstName = $userData['firstname'];
        $this->division = $userData['division'];

        if ($userData['staff'] != null) {
            $this->staff = Collection::make(explode(":", $userData['staff']));
        } else {
            $this->staff = Collection::make();
        }
    }

    public static function FromAPIRequest(IVAOApiServiceContract $IVAOAPI){
        $userData = $IVAOAPI->getUserData();
        return new self($userData);
    }

    public function joinGuild(Guild $guild, GuildServiceContract $guildService){
        $guildService->addMember($this->discordId, $guild->getId());
    }

    private function isStaff() {
        return $this->staff->isNotEmpty();
    }
    public function generateNickname() {
        if($this->isStaff()) {
            $nick = explode(" ", $this->firstName)[0]." | ";
            $this->staff->each(function($staff) use ($nick) {
               $nick .= " $staff";
            });
        } else {
            $nick = explode(" ", $this->firstName)[0]." - $this->vid";
        }

        return $nick;
    }
}
