<?php


namespace App\Infrastructure\Services;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;
use App\Domain\Entities\Roles;
use Illuminate\Support\Facades\Log;
use RestCord\DiscordClient;

class DiscordGuildService implements GuildServiceContract
{
    private $discordClient;
    private $guildId;

    public function __construct(DiscordClient $discordClient, $guildId)
    {
        $this->discordClient = $discordClient;
        $this->guildId = $guildId;
    }

    /**
     * @return mixed
     */
    public function getGuildId()
    {
        return $this->guildId;
    }

    public function addMember(Member $member, Guild $guild)
    {
        $this->discordClient->guild->addGuildMember([
            'guild.id' => (int)$guild->getId(),
            'user.id' => (int)$member->getDiscordId(),
            'access_token' => $member->getDiscordAccessToken()
        ]);

        $member->getRoles()->each(function ($role) use ($member, $guild) {
            $this->addRoleToMember($member, $role, $guild);
        });

        $this->changeMemberNickname($member, $guild);
    }

    private function addRoleToMember(Member $member, Roles $role, Guild $guild)
    {
        $this->discordClient->guild->addGuildMemberRole([
            'guild.id' => (int)$guild->getId(),
            'user.id' => (int)$member->getDiscordId(),
            'role.id' => $role->getId()
        ]);
    }

    private function changeMemberNickname(Member $member, Guild $guild)
    {
        $this->discordClient->guild->modifyGuildMember([
            'guild.id' => (int)$guild->getId(),
            'user.id' => (int)$member->getDiscordId(),
            'nick' => $member->generateNickname()
        ]);
    }

    public function getServerRoles(Guild $guild)
    {
        return $this->discordClient->guild->getGuildRoles([
            'guild.id' => (int)$guild->getId()
        ]);
    }
}
