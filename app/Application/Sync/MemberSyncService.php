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
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MemberSyncService
{
    public const LAST_RUN_CACHE_KEY = 'discord.sync.last_run';

    public const REQUESTED_CACHE_KEY = 'discord.sync.requested';

    /**
     * Asks the scheduler to sync every member on its next run, within a minute.
     */
    public function requestFullRun(): void
    {
        Cache::put(self::REQUESTED_CACHE_KEY, true, now()->addHour());
    }

    public function fullRunWasRequested(): bool
    {
        return (bool) Cache::pull(self::REQUESTED_CACHE_KEY);
    }

    private $directory;
    private $guildService;
    private $consentments;
    private $resolver;
    private $statuses;

    /** @var array<string, array|null> Discord members of the batch being synced */
    private $guildMembers = [];

    /** @var array<string, array|null> IVAO users of the batch being synced */
    private $ivaoUsers = [];

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

        $discordMember = array_key_exists($account->discordId, $this->guildMembers)
            ? $this->guildMembers[$account->discordId]
            : $this->guildService->getMember($account->discordId, $guild);

        if ($discordMember === null) {
            return SyncPlan::away();
        }

        $user = array_key_exists($account->userVid, $this->ivaoUsers)
            ? $this->ivaoUsers[$account->userVid]
            : $this->directory->find($account->userVid);

        // Without the IVAO account there is no proof of anything, and roles are never
        // taken away on the strength of an answer that did not come
        if ($user === null) {
            return SyncPlan::unverified($discordMember);
        }

        $member = new Member($user);
        $eligible = $this->resolver->isEligible($member);
        $hidden = $member->hasHiddenProfile();

        $desired = $eligible ? $this->resolver->rolesFor($member) : Collection::make();
        $current = Collection::make($discordMember['roles'] ?? []);

        // A hidden profile comes without the staff positions, so neither the roles that
        // depend on them nor the nickname can be decided from it
        $removable = $hidden
            ? $this->resolver->managedRoles()->diff($this->resolver->staffRoles())
            : $this->resolver->managedRoles();

        $titles = $this->storedTitles($account);

        // With a hidden profile and no positions kept from a login there is nothing to
        // build a staff nickname from, and the one already there is left alone
        $nickname = $eligible && (! $hidden || $titles !== null)
            ? $member->generateNickname($account->firstName, $titles)
            : null;

        return new SyncPlan(
            $discordMember,
            $member,
            $eligible,
            $desired->diff($current)->values()->all(),
            $current->intersect($removable)->diff($desired)->values()->all(),
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
        $summary = ['checked' => 0, 'updated' => 0, 'away' => 0, 'failed' => 0, 'removed' => 0];
        $statuses = [];
        $maxRemovals = max(1, (int) config('brauth.sync.max_removals'));
        $batchSize = max(1, (int) config('brauth.sync.batch_size'));

        foreach ($this->consentments->allActive()->chunk($batchSize) as $batch) {
            $this->prefetch($batch);

            foreach ($batch as $account) {
                $summary['checked']++;

                try {
                    $result = $this->apply($account, $this->plan($account));
                    $statuses[$account->id] = SyncStatusStore::forResult($result);
                    $summary['updated'] += $result->hasChanges() ? 1 : 0;
                    $summary['away'] += $result->status === SyncResult::AWAY ? 1 : 0;
                    $summary['removed'] += count($result->removed);
                    $progress && $progress($account, $result, null);
                } catch (\Throwable $e) {
                    $statuses[$account->id] = SyncStatusStore::FAILED;
                    $summary['failed']++;
                    Log::warning($e->getMessage(), ['event' => 'sync.failed', 'vid' => $account->userVid]);
                    $progress && $progress($account, null, $e);
                }

                // Taking roles from this many members at once is a sign of bad data, not
                // of that many members losing them on the same day
                if ($summary['removed'] > $maxRemovals) {
                    $summary['aborted'] = true;
                    Log::critical('Discord sync stopped after too many role removals', [
                        'event' => 'sync.aborted',
                    ] + $summary);
                    break 2;
                }
            }

            $this->forgetPrefetched();
            usleep((int) config('brauth.sync.delay_ms') * 1000);
        }

        $this->forgetPrefetched();

        $this->statuses->replace($statuses);
        Cache::forever(self::LAST_RUN_CACHE_KEY, $summary + ['finishedAt' => now()->toIso8601String()]);
        Log::notice('Discord sync finished', ['event' => 'sync.finished'] + $summary);

        return $summary;
    }

    /**
     * Asks Discord and IVAO about a whole batch at once, which is what makes the run quick.
     *
     * @param  \Illuminate\Support\Enumerable<int, ConsentmentModel>  $batch
     */
    private function prefetch(Enumerable $batch): void
    {
        $this->guildMembers = $this->guildService->getMembers(
            $batch->pluck('discordId')->unique()->all(),
            Guild::FromService($this->guildService)
        );

        $vids = $batch
            ->filter(fn (ConsentmentModel $account) => ($this->guildMembers[$account->discordId] ?? null) !== null)
            ->pluck('userVid')
            ->unique()
            ->all();

        $this->ivaoUsers = $vids ? $this->directory->findMany($vids) : [];
    }

    private function forgetPrefetched(): void
    {
        $this->guildMembers = [];
        $this->ivaoUsers = [];
    }

    private function apply(ConsentmentModel $account, SyncPlan $plan): SyncResult
    {
        if ($plan->isAway()) {
            return SyncResult::away();
        }

        if ($plan->unverified) {
            return SyncResult::unverified();
        }

        $this->rememberProfile($account, $plan->member);

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

    /**
     * Keeps the name and the positions IVAO exposes, so the nickname can still be built
     * for members whose profile is private.
     */
    private function rememberProfile(ConsentmentModel $account, ?Member $member): void
    {
        if ($member === null || $member->hasHiddenProfile()) {
            return;
        }

        $changes = [];
        $firstName = trim((string) $member->getFirstName());
        $positions = $member->getStaffTitles()->join(':');

        if ($firstName !== '' && $firstName !== $account->firstName) {
            $changes['firstName'] = mb_substr($firstName, 0, 64);
        }

        if ($positions !== (string) $account->staffPositions) {
            $changes['staffPositions'] = mb_substr($positions, 0, 512);
        }

        $changes && $account->update($changes);
    }

    /** @return string[]|null Positions kept from the last login, in nickname order */
    private function storedTitles(ConsentmentModel $account): ?array
    {
        $positions = trim((string) $account->staffPositions);

        return $positions === '' ? null : explode(':', $positions);
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
