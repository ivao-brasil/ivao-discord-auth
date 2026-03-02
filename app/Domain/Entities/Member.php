<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Member
{
    // IVAO Account Status Constants
    const STATUS_SUSPENDED = 0;
    const STATUS_INACTIVE = 1;
    const STATUS_ACTIVE = 2;
    const STATUS_ACTIVE_11 = 11;
    const STATUS_ACTIVE_12 = 12;

    private $vid;

    /**
     * @return mixed
     */
    public function getVid()
    {
        return $this->vid;
    }
    private $firstName;
    private $division;

    /**
     * @return mixed
     */
    public function getDivision()
    {
        return $this->division;
    }
    /** @var Collection */

    private $staff;
    private $discordId;
    private $discordAccessToken;
    /** @var Collection */

    private $roles;
    private $accountStatus;

    private $hoursAtc;
    private $hoursPilot;

    public function getTotalHours() {
        return $this->hoursAtc + $this->hoursPilot;
    }

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

        $this->accountStatus = $userData['rating'];
        $this->hoursAtc = $userData['hours_atc'] / 3600;
        $this->hoursPilot = $userData['hours_pilot'] / 3600;
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
        Log::info([
            'event' => 'assign.roles',
            'user' => $this->vid,
            'roles' => $roles->map(function($role) {
				return $role->getSuffix();
			})->toArray()
        ]);
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
        Log::info([
            'event' => 'join.server',
            'user' => $this->generateNickname()
        ]);
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

    /**
     * Check if account is active
     * @return bool
     */
    public function isActive() {
        return in_array($this->accountStatus, [
            self::STATUS_ACTIVE,
            self::STATUS_ACTIVE_11,
            self::STATUS_ACTIVE_12
        ]);
    }

    /**
     * Check if account is suspended
     * @return bool
     */
    public function isSuspended() {
        return $this->accountStatus === self::STATUS_SUSPENDED;
    }

    /**
     * Check if account is inactive
     * @return bool
     */
    public function isInactive() {
        return $this->accountStatus === self::STATUS_INACTIVE;
    }

    /**
     * Get the account status code
     * @return mixed
     */
    public function getAccountStatus()
    {
        return $this->accountStatus;
    }

    /**
     * Get human-readable account status
     * @return string
     */
    public function getAccountStatusReason(): string
    {
        if ($this->isSuspended()) {
            return 'suspended';
        }
        if ($this->isInactive()) {
            return 'inactive';
        }
        if (!$this->isActive()) {
            return 'not_active';
        }
        return 'active';
    }
}