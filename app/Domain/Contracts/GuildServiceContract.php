<?php


namespace App\Domain\Contracts;


use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;


interface GuildServiceContract
{
    public function addMember(Member $member, Guild $guild);
    public function getServerRoles(Guild $guild);
    public function getGuildId();
    public function getRolename(Guild $guild, string $roleId);
    public function removeFromServer($discordId, Guild $guild);

    /** The guild member as returned by Discord, or null when the user is not in the guild. */
    public function getMember(string $discordId, Guild $guild): ?array;

    /**
     * Several guild members at once, as a map of Discord id to member or null.
     * Ids Discord did not answer for are left out, to be fetched one by one.
     *
     * @param  string[]  $discordIds
     * @return array<string, array|null>
     */
    public function getMembers(array $discordIds, Guild $guild): array;

    /** Each change returns false when Discord refuses it because the member is ranked above the bot. */
    public function addRole(string $discordId, string $roleId, Guild $guild): bool;
    public function removeRole(string $discordId, string $roleId, Guild $guild): bool;
    public function setNickname(string $discordId, string $nickname, Guild $guild): bool;

    public function editInteractionResponse(string $interactionToken, string $content): void;
}
