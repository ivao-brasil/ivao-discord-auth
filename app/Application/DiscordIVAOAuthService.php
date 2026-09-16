<?php


namespace App\Application;

use App\Application\Contracts\DiscordIVAOAuthServiceInterface;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\Consentment;
use App\Domain\Entities\Member;
use App\Domain\Entities\Guild;
use App\Exceptions\InactiveAccountException;
use App\Exceptions\InvalidPermissionException;
use Illuminate\Support\Facades\Log;

class DiscordIVAOAuthService implements DiscordIVAOAuthServiceInterface
{
    private $IVAOAPIService;
    private $DiscordGuildService;
    private $RolesService;
    private $ConsentmentService;
    private $RoleResolver;

    public function __construct(
        IVAOApiServiceContract $IVAOAPIService,
        GuildServiceContract $DiscordGuildService,
        RolesServiceContract $RolesService,
        ConsentmentServiceContract $ConsentmentService,
        RoleResolver $RoleResolver
    )
    {
        $this->IVAOAPIService = $IVAOAPIService;
        $this->DiscordGuildService = $DiscordGuildService;
        $this->RolesService = $RolesService;
        $this->ConsentmentService = $ConsentmentService;
        $this->RoleResolver = $RoleResolver;
    }

    private function createConsentment(Member $member, $roles)
    {
        $consentment = new Consentment([
            'userVid' => $member->getVid(),
            'discordId' => $member->getDiscordId(),
            'firstName' => $member->getFirstName() ?: null,
            'nickName' => $member->generateNickname() ?? '',
            'roles' => $roles,
            'status' => true,
            'division' => $member->getDivision()
        ]);

        $this->ConsentmentService->create($consentment);
    }

    private function validateAccountStatus(Member $member): void
    {
        if ($member->isSuspended()) {
            Log::info([
                'event' => 'account.suspended',
                'user' => $member->generateNickname(),
                'vid' => $member->getVid()
            ]);
            throw new InactiveAccountException('suspended');
        }

        if ($member->isInactive()) {
            Log::info([
                'event' => 'account.inactive',
                'user' => $member->generateNickname(),
                'vid' => $member->getVid()
            ]);
            throw new InactiveAccountException('inactive');
        }

        if (!$member->isActive()) {
            Log::info([
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
            $this->validateAccountStatus($member);

            $roles = $this->RoleResolver->rolesFor($member);
            $guild = Guild::FromService($this->DiscordGuildService);

            if ($roles->isNotEmpty() && $this->RoleResolver->hasEnoughHours($member)) {
                $this->ConsentmentService->remove($member->getVid());
                if ($this->ConsentmentService->hasAnotherLinkedAccount($member->getVid(), $member->getDiscordId())) {
                    $accounts = $this->ConsentmentService->getAnotherLinkedAccounts($member->getVid(), $member->getDiscordId());

                    foreach ($accounts as $account) {
                        try {
                            $this->DiscordGuildService->removeFromServer($account['discordId'], $guild);
                        } catch (\Exception $e) {
                            // The account may have already left the server
                        }
                    }
                }
                $member->setRoles($roles);
                $member->joinGuild($guild, $this->DiscordGuildService);
                $this->createConsentment($member, $roles->map(function (string $roleId) use ($guild) {
                    return $this->DiscordGuildService->getRolename($guild, $roleId);
                })->filter()->join(':'));
            } else {
                if($roles->isEmpty()) {
                    Log::info([
                        'event' => 'roles.empty',
                        'user' => $member->generateNickname(),
                    ]);
                } else {
                    Log::info([
                        'event' => 'member.no.enough.hours',
                        'user' => $member->generateNickname(),
                        'hours' => $member->getTotalHours()
                    ]);
                }
                throw new InvalidPermissionException();
            }
        } catch (InactiveAccountException|InvalidPermissionException $e) {
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