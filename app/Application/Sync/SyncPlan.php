<?php

namespace App\Application\Sync;

use App\Domain\Entities\Member;

class SyncPlan
{
    public function __construct(
        /** Null when the account is not in the Discord server */
        public readonly ?array $discordMember,
        /** Null when the IVAO account no longer exists */
        public readonly ?Member $member,
        public readonly bool $eligible,
        /** @var string[] Discord role ids */
        public readonly array $add = [],
        /** @var string[] Discord role ids */
        public readonly array $remove = [],
        public readonly ?string $nickname = null,
    ) {
    }

    public static function away(): self
    {
        return new self(null, null, false);
    }

    public function isAway(): bool
    {
        return $this->discordMember === null;
    }

    public function hasChanges(): bool
    {
        return $this->add || $this->remove || $this->nickname !== null;
    }
}
