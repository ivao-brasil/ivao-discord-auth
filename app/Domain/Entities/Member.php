<?php


namespace App\Domain\Entities;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
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
    private $trialStaff;
    /** @var Collection */
    private $staffTitles;
    private $discordId;
    private $discordAccessToken;
    /** @var Collection */
    private $roles;
    private $accountStatus;
    private $atcRating;
    private $pilotRating;
    private $hasGca;
    private $ownsVirtualAirline;

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

        $this->readStaffPositions($userData['userStaffPositions'] ?? []);

        $this->accountStatus = $userData['rating']['networkRating']['id'] ?? null;
        $this->atcRating = $userData['rating']['atcRating']['id'] ?? null;
        $this->pilotRating = $userData['rating']['pilotRating']['id'] ?? null;
        $this->hasGca = ! empty($userData['gcas']);
        $this->ownsVirtualAirline = ! empty($userData['ownedVirtualAirlines']);

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

    /**
     * Replaces what the user endpoint said about positions, which comes empty for
     * private profiles, with the ones listed for this VID by the network.
     *
     * @param  array<int, array>  $positions
     */
    public function useStaffPositions(array $positions): void
    {
        $this->readStaffPositions($positions);
    }

    /** @param array<int, array> $positions */
    private function readStaffPositions(array $positions): void
    {
        $positions = Collection::make($positions)->filter(fn ($position) => ! empty($position['id']));

        $this->staff = $positions->pluck('id')->values();
        $this->trialStaff = $positions->where('onTrial', true)->pluck('id')->values();

        // HQ positions come as IVAO-XX in connectAs and are listed after division positions
        $this->staffTitles = $positions
            ->map(fn ($position) => $position['connectAs'] ?? $position['id'])
            ->partition(fn ($title) => ! str_starts_with($title, 'IVAO-'))
            ->flatten()
            ->values();
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function getStaff(): Collection
    {
        return $this->staff;
    }

    /** @return Collection<int, string> Positions as they appear in the nickname */
    public function getStaffTitles(): Collection
    {
        return $this->staffTitles;
    }

    public function getStaffPositions(bool $includeTrial = true): Collection
    {
        return $includeTrial ? $this->staff : $this->staff->diff($this->trialStaff)->values();
    }

    public function getAtcRating(): ?int
    {
        return $this->atcRating;
    }

    public function getPilotRating(): ?int
    {
        return $this->pilotRating;
    }

    public function hasGca(): bool
    {
        return $this->hasGca;
    }

    public function ownsVirtualAirline(): bool
    {
        return $this->ownsVirtualAirline;
    }

    /**
     * @return Collection<int, string> Discord role ids
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param Collection<int, string> $roles Discord role ids
     */
    public function setRoles(Collection $roles): void
    {
        $this->roles = $roles;
        Log::info([
            'event' => 'assign.roles',
            'user' => $this->vid,
            'roles' => $roles->all(),
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

    /**
     * The nickname for this member, or null when neither IVAO nor the given name is available.
     *
     * @param  string|null  $knownFirstName  name kept from the member's last login
     */
    public function generateNickname(?string $knownFirstName = null, ?array $knownTitles = null): ?string
    {
        $firstName = explode(' ', trim($this->firstName ?: (string) $knownFirstName))[0];

        if ($firstName === '') {
            return null;
        }

        // A hidden profile comes without the positions, so the ones kept from the login stand in
        $titles = $this->hasHiddenProfile() && $knownTitles !== null
            ? Collection::make($knownTitles)->filter()->values()
            : $this->staffTitles;

        if ($titles->isEmpty()) {
            return mb_substr("$firstName - $this->vid", 0, self::NICKNAME_MAX_LENGTH);
        }

        // Discord rejects longer nicknames, so drop positions from the end until it fits
        do {
            $nick = "$firstName | ".self::groupTitles($titles);
            $titles = $titles->slice(0, -1);
        } while (mb_strlen($nick) > self::NICKNAME_MAX_LENGTH && $titles->isNotEmpty());

        return mb_substr($nick, 0, self::NICKNAME_MAX_LENGTH);
    }

    /**
     * Positions of the same division are written once: BR-WM and BR-MA1 become BR/WM/MA1,
     * which leaves room for the positions that would otherwise be cut off the end.
     *
     * @param  Enumerable<int, string>  $titles
     */
    private static function groupTitles(Enumerable $titles): string
    {
        return $titles
            ->groupBy(fn (string $title) => explode('-', $title, 2)[0])
            ->map(fn (Enumerable $group, string $prefix) => $group->count() === 1
                ? $group->first()
                : $prefix.'/'.$group->map(fn (string $title) => explode('-', $title, 2)[1] ?? $title)->join('/'))
            ->join(' ');
    }

    /**
     * IVAO hides the name and the staff positions of members who keep their profile private,
     * so what came back cannot be used to decide that someone is no longer staff.
     */
    public function hasHiddenProfile(): bool
    {
        return trim((string) $this->firstName) === '';
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