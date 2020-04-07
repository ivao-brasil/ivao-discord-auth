<?php


namespace App\Services\Contracts;


use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;
use App\Domain\Entities\Roles;

interface GuildServiceContract
{
    public function addMember(Member $member, Guild $guild);
    public function getServerRoles(Guild $guild);
}
