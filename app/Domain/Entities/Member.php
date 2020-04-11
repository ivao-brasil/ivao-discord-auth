<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Support\Collection;

class Member
{
    private $vid;
    private $firstName;
    private $division;
    /** @var Collection */

    private $staff;
    private $discordId;
    private $discordAccessToken;
    /** @var Collection */

    private $roles;

    /**
     * Member constructor.
     * @param $IVAOTOKEN
     */
    public function __construct($userData)
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

    public static function FromAPIRequest(IVAOApiServiceContract $IVAOAPI)
    {
        $userData = $IVAOAPI->getUserData();
        return new self($userData);
    }

    /**
     * @return mixed
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @return Collection
     */
    public function getStaff(): Collection
    {
        return $this->staff;
    }

    /**
     * @return Collection
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param Collection $roles
     */
    public function setRoles(Collection $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @return mixed
     */
    public function getDiscordAccessToken()
    {
        return $this->discordAccessToken;
    }

    /**
     * @param mixed $discordAccessToken
     */
    public function setDiscordAccessToken($discordAccessToken): void
    {
        $this->discordAccessToken = $discordAccessToken;
    }

    /**
     * @return mixed
     */
    public function getDiscordId()
    {
        return $this->discordId;
    }

    /**
     * @param mixed $discordId
     */
    public function setDiscordId($discordId): void
    {
        $this->discordId = $discordId;
    }

    public function joinGuild(Guild $guild, GuildServiceContract $guildService)
    {
        $guildService->addMember($this, $guild);
    }

    public function generateNickname()
    {
        if ($this->isStaff()) {
            $nick = explode(" ", $this->firstName)[0] . " | " . $this->staff->join(' ');
        } else {
            $nick = explode(" ", $this->firstName)[0] . " - $this->vid";
        }
        return $nick;
    }

    public function isStaff()
    {
        return $this->staff->isNotEmpty();
    }
}
