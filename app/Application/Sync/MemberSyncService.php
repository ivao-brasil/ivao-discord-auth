<?php

namespace App\Application\Sync;

use App\Application\RoleResolver;
use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MemberSyncService
{
    public const LAST_RUN_CACHE_KEY = 'discord.sync.last_run';

    private $directory;
    private $guildService;
    private $consentments;
    private $resolver;

    public function __construct(
        IVAOUserDirectoryContract $directory,
        GuildServiceContract $guildService,
        ConsentmentServiceContract $consentments,
        RoleResolver $resolver
    ) {
        $this->directory = $directory;
        $this->guildService = $guildService;
        $this->consentments = $consentments;
        $this->resolver = $resolver;
    }

    /**
     * Brings the member's managed roles and nickname in line with their current IVAO data.
     *
     * @throws \Illuminate\Http\Client\RequestException when IVAO or Discord cannot be reached; nothing is removed in that case
     */
    public function sync(ConsentmentModel $account): SyncResult
    {
        $guild = Guild::FromService($this->guildService);
        $discordMember = $this->guildService->getMember($account->discordId, $guild);

        if ($discordMember === null) {
            return SyncResult::away();
        }

        $user = $this->directory->find($account->userVid);
        $member = $user ? new Member($user) : null;
        $eligible = $member !== null && $this->resolver->isEligible($member);

        $desired = $eligible ? $this->resolver->rolesFor($member) : Collection::make();
        $current = Collection::make($discordMember['roles'] ?? []);

        $added = [];
        $removed = [];
        $skipped = false;

        foreach ($desired->diff($current) as $roleId) {
            $this->guildService->addRole($account->discordId, $roleId, $guild) ? $added[] = $roleId : $skipped = true;
        }

        foreach ($current->intersect($this->resolver->managedRoles())->diff($desired) as $roleId) {
            $this->guildService->removeRole($account->discordId, $roleId, $guild) ? $removed[] = $roleId : $skipped = true;
        }

        $nickname = null;
        if ($eligible && ($discordMember['nick'] ?? null) !== $member->generateNickname()) {
            $this->guildService->setNickname($account->discordId, $member->generateNickname(), $guild)
                ? $nickname = $member->generateNickname()
                : $skipped = true;
        }

        $result = new SyncResult(SyncResult::SYNCED, $added, $removed, $nickname, $skipped);

        if ($result->hasChanges()) {
            $this->recordChanges($account, $result, $current, $guild);
        }

        return $result;
    }

    /**
     * Syncs every linked account and stores a summary of the run.
     *
     * @param  callable(ConsentmentModel, ?SyncResult, ?\Throwable): void|null  $progress
     */
    public function syncAll(?callable $progress = null): array
    {
        $summary = ['checked' => 0, 'updated' => 0, 'away' => 0, 'failed' => 0];

        foreach ($this->consentments->allActive() as $account) {
            $summary['checked']++;

            try {
                $result = $this->sync($account);
                $summary['updated'] += $result->hasChanges() ? 1 : 0;
                $summary['away'] += $result->status === SyncResult::AWAY ? 1 : 0;
                $progress && $progress($account, $result, null);
            } catch (\Throwable $e) {
                $summary['failed']++;
                Log::warning($e->getMessage(), ['event' => 'sync.failed', 'vid' => $account->userVid]);
                $progress && $progress($account, null, $e);
            }

            usleep((int) config('brauth.sync.delay_ms') * 1000);
        }

        Cache::forever(self::LAST_RUN_CACHE_KEY, $summary + ['finishedAt' => now()->toIso8601String()]);
        Log::notice('Discord sync finished', ['event' => 'sync.finished'] + $summary);

        return $summary;
    }

    private function recordChanges(ConsentmentModel $account, SyncResult $result, Collection $current, Guild $guild): void
    {
        $roles = $current->diff($result->removed)->merge($result->added)
            ->intersect($this->resolver->managedRoles())
            ->map(fn (string $roleId) => $this->guildService->getRolename($guild, $roleId))
            ->filter()
            ->join(':');

        $this->consentments->updateSynced($account, $result->nickname ?? $account->nickName, $roles);

        Log::info([
            'event' => 'sync.updated',
            'vid' => $account->userVid,
            'added' => $result->added,
            'removed' => $result->removed,
            'nickname' => $result->nickname,
        ]);
    }
}
