<?php


namespace App\Domain\Contracts;


use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;


interface GuildServiceContract
{
    public function addMember(Member $member, Guild $guild);
    public function getServerRoles(Guild $guild);
    public function getGuildId();
}
