<?php


namespace App\Domain\Contracts;


use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;
use App\Domain\Entities\Roles;


interface GuildServiceContract
{
    public function addMember(Member $member, Guild $guild);
    public function getServerRoles(Guild $guild);
    public function getGuildId();
    public function getRolename(Guild $guild, Roles $role);
    public function removeFromServer($discorId, Guild $guild);
}
