<?php


namespace App\Application;

use App\Application\Contracts\DiscordIVAOAuthServiceInterface;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Contracts\RolesServiceContract;
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

    /**
     * DiscordIVAOAuthService constructor.
     * @param $IVAOAPIService
     * @param $DiscordGuildService
     * @param $RolesService
     */
    public function __construct(IVAOApiServiceContract $IVAOAPIService, GuildServiceContract $DiscordGuildService, RolesServiceContract $RolesService)
    {
        $this->IVAOAPIService = $IVAOAPIService;
        $this->DiscordGuildService = $DiscordGuildService;
        $this->RolesService = $RolesService;
    }

    /**
     * DiscordIVAOAuthService constructor.
     * @param $IVAOAPIService
     * @param $member
     * @param $DiscordGuildService
     */

    private function getRolesToAssign(Member $member){
        return Roles::FromAPIRequest($this->RolesService)->filter(function (Roles $role) use ($member) {
            if($role->getSuffix() === 'Member') {
                return true;
            }
            else if($member->isStaff()) {
                return $member->getStaff()->contains(function($staff) use ($role) {
                    $suffix = Collection::make(explode(':', $role->getSuffix()));
                    return $suffix->contains($staff);
                });
            }
            else {
                return false;
            }
        });
    }

    public function validateMember(Member $member)
    {
        try {
            $roles = $this->getRolesToAssign($member);
            $guild = Guild::FromService($this->DiscordGuildService);
            if($roles->isNotEmpty()) {
                $member->setRoles($roles);
                $member->joinGuild($guild, $this->DiscordGuildService);
            } else {
                throw new InvalidPermissionException();
            }
        } catch (\Exception $e){
            throw new InvalidPermissionException();
        }
    }
}
