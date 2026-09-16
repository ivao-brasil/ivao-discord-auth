<?php

namespace App\Application\Sync;

class SyncResult
{
    public const SYNCED = 'synced';
    public const AWAY = 'away';

    public const UNVERIFIED = 'unverified';

    public function __construct(
        public readonly string $status,
        /** @var string[] Discord role ids */
        public readonly array $added = [],
        /** @var string[] Discord role ids */
        public readonly array $removed = [],
        public readonly ?string $nickname = null,
        /** Some changes were refused because the member is ranked above the bot */
        public readonly bool $skipped = false,
    ) {
    }

    public static function away(): self
    {
        return new self(self::AWAY);
    }

    public static function unverified(): self
    {
        return new self(self::UNVERIFIED);
    }

    public function hasChanges(): bool
    {
        return $this->added || $this->removed || $this->nickname !== null;
    }
}
