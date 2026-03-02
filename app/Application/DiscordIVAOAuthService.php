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
use App\Exceptions\InactiveAccountException;
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
            'roles' => $roles,
            'status' => true,
            'division' => $member->getDivision()
        ]);

        $this->ConsentmentService->create($consentment);
    }

    /**
     * Validate member account status before proceeding
     * Throws InactiveAccountException if account is suspended or inactive
     * 
     * @param Member $member
     * @throws InactiveAccountException
     */
    private function validateAccountStatus(Member $member): void
    {
        // Check if account is suspended
        if ($member->isSuspended()) {
            Log::warning([
                'event' => 'account.suspended',
                'user' => $member->generateNickname(),
                'vid' => $member->getVid()
            ]);
            throw new InactiveAccountException('suspended');
        }

        // Check if account is inactive
        if ($member->isInactive()) {
            Log::warning([
                'event' => 'account.inactive',
                'user' => $member->generateNickname(),
                'vid' => $member->getVid()
            ]);
            throw new InactiveAccountException('inactive');
        }

        // Check if account is not in active status
        if (!$member->isActive()) {
            Log::warning([
                'event' => 'account.not_active',
                'user' => $member->generateNickname(),
                'vid' => $member->getVid(),
                'status' => $member->getAccountStatus()
            ]);
            throw new InactiveAccountException($member->getAccountStatusReason());
        }
    }

    public function validateMember(Member $member)
    {
        try {
            // First, validate account status (suspended/inactive check)
            $this->validateAccountStatus($member);

            $roles = $this->getRolesToAssign($member);
            $guild = Guild::FromService($this->DiscordGuildService);
            
            if ($roles->isNotEmpty() && $member->getTotalHours() > 5) {
                $this->ConsentmentService->remove($member->getVid());
                if ($this->ConsentmentService->hasAnotherLinkedAccount($member->getVid(), $member->getDiscordId())) {
                    $accounts = $this->ConsentmentService->getAnotherLinkedAccounts($member->getVid(), $member->getDiscordId());

                    foreach ($accounts as $account) {

                        try {
                            $this->DiscordGuildService->removeFromServer($account['discordId'], $guild);
                        } catch (\Exception $e) {
                            // NOOP
                        }
                    }
                }
                $member->setRoles($roles);
                $member->joinGuild($guild, $this->DiscordGuildService);
                $this->createConsentment($member, $roles->map(function (Roles $role) use ($guild) {
                    return $this->DiscordService->getRolename($guild, $role);
                })->join(':'));
            } else {
                if($roles->isEmpty()) {
                    Log::info([
                        'event' => 'roles.empty',
                        'user' => $member->generateNickname(),
                    ]);
                } else if($member->getTotalHours() < 5) {
                    Log::info([
                        'event' => 'member.no.enough.hours',
                        'user' => $member->generateNickname(),
                        'hours' => $member->getTotalHours()
                    ]);
                }
                throw new InvalidPermissionException();
            }
        } catch (InactiveAccountException $e) {
            // Re-throw InactiveAccountException to be handled by the exception handler
            throw $e;
        } catch (\Exception $e) {
            Log::critical($e, [
                'event' => 'discord.exception',
                'user' => $member->generateNickname()
            ]);

            throw new InvalidPermissionException();
        }
    }
}