<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Member
{
    // IVAO network rating ids
    const STATUS_SUSPENDED = 0;
    const STATUS_INACTIVE = 1;
    const STATUS_ACTIVE = 2;
    const STATUS_ACTIVE_11 = 11;
    const STATUS_ACTIVE_12 = 12;

    const NICKNAME_MAX_LENGTH = 32;

    private $vid;

    public function getVid()
    {
        return $this->vid;
    }
    private $firstName;
    private $division;

    public function getDivision()
    {
        return $this->division;
    }
    /** @var Collection */

    private $staff;
    /** @var Collection */
    private $staffTitles;
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
     * @param array $userData IVAO API v2 user (/v2/users/me)
     */
    public function __construct(array $userData)
    {
        $this->vid = (string) $userData['id'];
        $this->firstName = $userData['firstName'] ?? '';
        $this->division = $userData['divisionId'] ?? null;

        $positions = Collection::make($userData['userStaffPositions'] ?? [])->filter(fn ($position) => ! empty($position['id']));

        $this->staff = $positions->pluck('id')->values();

        // HQ positions come as IVAO-XX in connectAs and are listed after division positions
        $this->staffTitles = $positions
            ->map(fn ($position) => $position['connectAs'] ?? $position['id'])
            ->partition(fn ($title) => ! str_starts_with($title, 'IVAO-'))
            ->flatten()
            ->values();

        $this->accountStatus = $userData['rating']['networkRating']['id'] ?? null;

        // IVAO reports hours in seconds
        $hours = Collection::make($userData['hours'] ?? [])->pluck('hours', 'type');
        $this->hoursAtc = ($hours['atc'] ?? 0) / 3600;
        $this->hoursPilot = ($hours['pilot'] ?? 0) / 3600;
    }

    public static function FromAPIRequest(IVAOApiServiceContract $IVAOAPI)
    {
        $userData = $IVAOAPI->getUserData();
        return new self($userData);
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function getStaff(): Collection
    {
        return $this->staff;
    }

    public function getRoles()
    {
        return $this->roles;
    }

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

    public function getDiscordAccessToken()
    {
        return $this->discordAccessToken;
    }

    public function setDiscordAccessToken($discordAccessToken): void
    {
        $this->discordAccessToken = $discordAccessToken;
    }

    public function getDiscordId()
    {
        return $this->discordId;
    }

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
        $firstName = explode(' ', $this->firstName)[0];

        if (! $this->isStaff()) {
            return mb_substr("$firstName - $this->vid", 0, self::NICKNAME_MAX_LENGTH);
        }

        // Discord rejects longer nicknames, so drop positions from the end until it fits
        $titles = $this->staffTitles;
        do {
            $nick = "$firstName | ".$titles->join(' ');
            $titles = $titles->slice(0, -1);
        } while (mb_strlen($nick) > self::NICKNAME_MAX_LENGTH && $titles->isNotEmpty());

        return mb_substr($nick, 0, self::NICKNAME_MAX_LENGTH);
    }

    public function isStaff()
    {
        return $this->staff->isNotEmpty();
    }

    public function isActive() {
        return in_array($this->accountStatus, [
            self::STATUS_ACTIVE,
            self::STATUS_ACTIVE_11,
            self::STATUS_ACTIVE_12
        ]);
    }

    public function isSuspended() {
        return $this->accountStatus === self::STATUS_SUSPENDED;
    }

    public function isInactive() {
        return $this->accountStatus === self::STATUS_INACTIVE;
    }

    public function getAccountStatus()
    {
        return $this->accountStatus;
    }

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