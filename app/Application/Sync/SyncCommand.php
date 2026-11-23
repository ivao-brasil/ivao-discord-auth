<?php

namespace App\Application\Sync;

use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Entities\Guild;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Respostas do /sync, usadas tanto pelo endpoint HTTP quanto pelo bot no gateway.
 */
class SyncCommand
{
    private $sync;
    private $consentments;
    private $guildService;

    public function __construct(MemberSyncService $sync, ConsentmentServiceContract $consentments, GuildServiceContract $guildService)
    {
        $this->sync = $sync;
        $this->consentments = $consentments;
        $this->guildService = $guildService;
    }

    /**
     * A resposta que não depende da sync, ou null quando ela deve rodar (o cooldown já fica marcado).
     */
    public function answer(string $discordId, bool $everyone): ?string
    {
        $account = $this->consentments->findActiveByDiscordId($discordId);

        if ($account === null) {
            return __('text.syncNotLinked', ['url' => config('app.url')]);
        }

        if ($everyone) {
            return $this->syncEveryone((string) $account->userVid);
        }

        $cooldown = now()->addMinutes(config('brauth.sync.cooldown_minutes'));
        if (! Cache::add("discord.sync.cooldown.{$discordId}", true, $cooldown)) {
            return __('text.syncCooldown', ['minutes' => config('brauth.sync.cooldown_minutes')]);
        }

        return null;
    }

    public function run(string $discordId): string
    {
        $account = $this->consentments->findActiveByDiscordId($discordId);

        if ($account === null) {
            return __('text.syncNotLinked', ['url' => config('app.url')]);
        }

        try {
            return $this->describe($this->sync->sync($account));
        } catch (\Throwable $e) {
            Log::warning($e->getMessage(), ['event' => 'sync.command.failed', 'vid' => $account->userVid]);

            return __('text.syncFailed');
        }
    }

    private function syncEveryone(string $vid): string
    {
        if (! in_array($vid, config('brauth.admin_vids'), true)) {
            return __('text.syncEveryoneNotAllowed');
        }

        $this->sync->requestFullRun();

        Log::notice('Full sync requested', ['event' => 'sync.requested', 'vid' => $vid]);

        return __('text.syncEveryoneQueued');
    }

    private function describe(SyncResult $result): string
    {
        if ($result->status === SyncResult::AWAY) {
            return __('text.syncAway', ['url' => config('app.url')]);
        }

        $guild = Guild::FromService($this->guildService);
        $names = fn (array $roleIds) => collect($roleIds)
            ->map(fn (string $roleId) => $this->guildService->getRolename($guild, $roleId) ?? $roleId)
            ->join(', ');

        $lines = [$result->hasChanges() ? __('text.syncUpdated') : __('text.syncUpToDate')];

        if ($result->added) {
            $lines[] = __('text.syncRolesAdded', ['roles' => $names($result->added)]);
        }

        if ($result->removed) {
            $lines[] = __('text.syncRolesRemoved', ['roles' => $names($result->removed)]);
        }

        if ($result->nickname !== null) {
            $lines[] = __('text.syncNickname', ['nickname' => $result->nickname]);
        }

        if ($result->skipped) {
            $lines[] = __('text.syncSkipped');
        }

        return implode("\n", $lines);
    }
}
