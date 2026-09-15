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
    private $statuses;

    public function __construct(
        IVAOUserDirectoryContract $directory,
        GuildServiceContract $guildService,
        ConsentmentServiceContract $consentments,
        RoleResolver $resolver,
        SyncStatusStore $statuses
    ) {
        $this->directory = $directory;
        $this->guildService = $guildService;
        $this->consentments = $consentments;
        $this->resolver = $resolver;
        $this->statuses = $statuses;
    }

    /**
     * Works out the changes that bring the member in line with their current IVAO data, without applying them.
     *
     * @throws \Illuminate\Http\Client\RequestException when IVAO or Discord cannot be reached
     */
    public function plan(ConsentmentModel $account): SyncPlan
    {
        $guild = Guild::FromService($this->guildService);
        $discordMember = $this->guildService->getMember($account->discordId, $guild);

        if ($discordMember === null) {
            return SyncPlan::away();
        }

        $user = $this->directory->find($account->userVid);
        $member = $user ? new Member($user) : null;
        $eligible = $member !== null && $this->resolver->isEligible($member);

        $desired = $eligible ? $this->resolver->rolesFor($member) : Collection::make();
        $current = Collection::make($discordMember['roles'] ?? []);
        $nickname = $eligible ? $member->generateNickname() : null;

        return new SyncPlan(
            $discordMember,
            $member,
            $eligible,
            $desired->diff($current)->values()->all(),
            $current->intersect($this->resolver->managedRoles())->diff($desired)->values()->all(),
            $nickname !== null && $nickname !== ($discordMember['nick'] ?? null) ? $nickname : null,
        );
    }

    /**
     * Applies the planned changes. Nothing is removed when IVAO or Discord cannot be reached.
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function sync(ConsentmentModel $account): SyncResult
    {
        try {
            $result = $this->apply($account, $this->plan($account));
        } catch (\Throwable $e) {
            $this->statuses->put($account->id, SyncStatusStore::FAILED);

            throw $e;
        }

        $this->statuses->put($account->id, SyncStatusStore::forResult($result));

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
        $statuses = [];

        foreach ($this->consentments->allActive() as $account) {
            $summary['checked']++;

            try {
                $result = $this->apply($account, $this->plan($account));
                $statuses[$account->id] = SyncStatusStore::forResult($result);
                $summary['updated'] += $result->hasChanges() ? 1 : 0;
                $summary['away'] += $result->status === SyncResult::AWAY ? 1 : 0;
                $progress && $progress($account, $result, null);
            } catch (\Throwable $e) {
                $statuses[$account->id] = SyncStatusStore::FAILED;
                $summary['failed']++;
                Log::warning($e->getMessage(), ['event' => 'sync.failed', 'vid' => $account->userVid]);
                $progress && $progress($account, null, $e);
            }

            usleep((int) config('brauth.sync.delay_ms') * 1000);
        }

        $this->statuses->replace($statuses);
        Cache::forever(self::LAST_RUN_CACHE_KEY, $summary + ['finishedAt' => now()->toIso8601String()]);
        Log::notice('Discord sync finished', ['event' => 'sync.finished'] + $summary);

        return $summary;
    }

    private function apply(ConsentmentModel $account, SyncPlan $plan): SyncResult
    {
        if ($plan->isAway()) {
            return SyncResult::away();
        }

        $guild = Guild::FromService($this->guildService);
        $added = [];
        $removed = [];
        $skipped = false;

        foreach ($plan->add as $roleId) {
            $this->guildService->addRole($account->discordId, $roleId, $guild) ? $added[] = $roleId : $skipped = true;
        }

        foreach ($plan->remove as $roleId) {
            $this->guildService->removeRole($account->discordId, $roleId, $guild) ? $removed[] = $roleId : $skipped = true;
        }

        $nickname = null;
        if ($plan->nickname !== null) {
            $this->guildService->setNickname($account->discordId, $plan->nickname, $guild)
                ? $nickname = $plan->nickname
                : $skipped = true;
        }

        $result = new SyncResult(SyncResult::SYNCED, $added, $removed, $nickname, $skipped);

        if ($result->hasChanges()) {
            $this->recordChanges($account, $result, Collection::make($plan->discordMember['roles'] ?? []), $guild);
        }

        return $result;
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
