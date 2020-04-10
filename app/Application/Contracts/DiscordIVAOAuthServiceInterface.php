<?php


namespace App\Application\Contracts;


use App\Domain\Entities\Member;

interface DiscordIVAOAuthServiceInterface
{
    public function validateMember(Member $member);
}
