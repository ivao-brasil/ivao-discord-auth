<?php

namespace App\Application\Sync;

use Illuminate\Support\Facades\Cache;

/**
 * Outcome of the latest sync of each linked account, used by the admin to filter members.
 */
class SyncStatusStore
{
    public const OK = 'ok';
    public const AWAY = 'away';
    public const PENDING = 'pending';
    public const FAILED = 'failed';

    private const CACHE_KEY = 'discord.sync.statuses';

    public function all(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    public function get(int $accountId): ?array
    {
        return $this->all()[$accountId] ?? null;
    }

    /** @return int[] */
    public function accountsWith(string $status): array
    {
        return array_keys(array_filter($this->all(), fn (array $entry) => $entry['status'] === $status));
    }

    public function put(int $accountId, string $status): void
    {
        $entries = $this->all();
        $entries[$accountId] = ['status' => $status, 'at' => now()->toIso8601String()];

        Cache::forever(self::CACHE_KEY, $entries);
    }

    /**
     * Replaces every status after a full sync.
     *
     * @param  array<int, string>  $statuses  status by account id
     */
    public function replace(array $statuses): void
    {
        $now = now()->toIso8601String();

        Cache::forever(self::CACHE_KEY, array_map(fn (string $status) => ['status' => $status, 'at' => $now], $statuses));
    }

    public static function forResult(SyncResult $result): string
    {
        if ($result->status === SyncResult::AWAY) {
            return self::AWAY;
        }

        if ($result->status === SyncResult::UNVERIFIED) {
            return self::FAILED;
        }

        return $result->skipped ? self::PENDING : self::OK;
    }
}
