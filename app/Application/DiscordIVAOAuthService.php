<?php


namespace App\Application;

use App\Application\Contracts\DiscordIVAOAuthServiceInterface;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\Consentment;
use App\Domain\Entities\Member;
use App\Domain\Entities\Roles;
use App\Domain\Entities\Guild;
use App\Exceptions\GeneralErrorException;
use App\Exceptions\InvalidPermissionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DiscordIVAOAuthService implements DiscordIVAOAuthServiceInterface
{
    private $IVAOAPIService;
    private $DiscordGuildService;
    private $RolesService;
    private $ConsentmentService;
    private $DiscordService;

    /**
     * DiscordIVAOAuthService constructor.
     * @param $IVAOAPIService
     * @param $DiscordGuildService
     * @param $RolesService
     */
    public function __construct(
        IVAOApiServiceContract $IVAOAPIService,
        GuildServiceContract $DiscordGuildService,
        RolesServiceContract $RolesService,
        ConsentmentServiceContract $ConsentmentService,
        GuildServiceContract $DiscordService
    )
    {
        $this->IVAOAPIService = $IVAOAPIService;
        $this->DiscordGuildService = $DiscordGuildService;
        $this->RolesService = $RolesService;
        $this->ConsentmentService = $ConsentmentService;
        $this->DiscordService = $DiscordService;
    }

    /**
     * DiscordIVAOAuthService constructor.
     * @param $IVAOAPIService
     * @param $member
     * @param $DiscordGuildService
     */

    private function getRolesToAssign(Member $member)
    {
        return Roles::FromAPIRequest($this->RolesService)->filter(function (Roles $role) use ($member) {
            if ($role->getSuffix() === 'Member') {
                return true;
            } else if ($member->isStaff()) {
                return $member->getStaff()->contains(function ($staff) use ($role) {
                    $suffix = Collection::make(explode(':', $role->getSuffix()));
                    return $suffix->contains($staff);
                });
            } else {
                return false;
            }
        });
    }

    private function createConsentment(Member $member, $roles)
    {
        $consentment = new Consentment([
            'userVid' => $member->getVid(),
            'discordId' => $member->getDiscordId(),
            'nickName' => $member->generateNickname(),
            'roles' => $roles
        ]);

        $this->ConsentmentService->create($consentment);
    }

    public function validateMember(Member $member)
    {
        try {
            $roles = $this->getRolesToAssign($member);
            $guild = Guild::FromService($this->DiscordGuildService);
            if ($roles->isNotEmpty() && $member->isActive()) {
                if ($this->ConsentmentService->hasAnotherLinkedAccount($member->getVid(), $member->getDiscordId())) {
                    $accounts = $this->ConsentmentService->getAnotherLinkedAccounts($member->getVid(), $member->getDiscordId());

                    foreach ($accounts as $account) {

                        try {
                            $this->DiscordGuildService->removeFromServer($account['discordId'], $guild);
                        } catch (\Exception $e) {

                        }
                    }
                }
                $member->setRoles($roles);
                $member->joinGuild($guild, $this->DiscordGuildService);
                $this->createConsentment($member, $roles->map(function (Roles $role) use ($guild) {
                    return $this->DiscordService->getRolename($guild, $role);
                })->join(':'));
            } else {
                throw new InvalidPermissionException();
            }
        } catch (\Exception $e) {
            throw new InvalidPermissionException();
        }
    }
}
