<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Sync\MemberSyncService;
use App\Application\Sync\SyncResult;
use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Entities\Guild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DiscordInteractionController extends Controller
{
    private const TYPE_PING = 1;
    private const TYPE_APPLICATION_COMMAND = 2;

    private const RESPONSE_PONG = 1;
    private const RESPONSE_MESSAGE = 4;
    private const RESPONSE_DEFERRED_MESSAGE = 5;

    private const FLAG_EPHEMERAL = 64;

    public const OPTION_EVERYONE = 'todos';

    private $sync;
    private $consentments;
    private $guildService;

    public function __construct(MemberSyncService $sync, ConsentmentServiceContract $consentments, GuildServiceContract $guildService)
    {
        $this->sync = $sync;
        $this->consentments = $consentments;
        $this->guildService = $guildService;
    }

    public function __invoke(Request $request): JsonResponse
    {
        return match ($request->integer('type')) {
            self::TYPE_PING => response()->json(['type' => self::RESPONSE_PONG]),
            self::TYPE_APPLICATION_COMMAND => $this->command($request),
            default => response()->json(['message' => 'Unsupported interaction'], 400),
        };
    }

    private function command(Request $request): JsonResponse
    {
        if ($request->input('data.name') !== 'sync') {
            return $this->reply(__('text.syncUnknownCommand'));
        }

        $discordId = (string) $request->input('member.user.id', $request->input('user.id'));
        $account = $this->consentments->findActiveByDiscordId($discordId);

        if ($account === null) {
            return $this->reply(__('text.syncNotLinked', ['url' => config('app.url')]));
        }

        if ($this->wantsEveryone($request)) {
            return $this->syncEveryone($account);
        }

        $cooldown = now()->addMinutes(config('brauth.sync.cooldown_minutes'));
        if (! Cache::add("discord.sync.cooldown.{$discordId}", true, $cooldown)) {
            return $this->reply(__('text.syncCooldown', ['minutes' => config('brauth.sync.cooldown_minutes')]));
        }

        $token = (string) $request->input('token');

        // Discord expects an answer within 3 seconds, so the sync runs after the deferred response is sent
        defer(function () use ($account, $token) {
            try {
                $message = $this->describe($this->sync->sync($account));
            } catch (\Throwable $e) {
                Log::warning($e->getMessage(), ['event' => 'sync.command.failed', 'vid' => $account->userVid]);
                $message = __('text.syncFailed');
            }

            $this->guildService->editInteractionResponse($token, $message);
        });

        return response()->json([
            'type' => self::RESPONSE_DEFERRED_MESSAGE,
            'data' => ['flags' => self::FLAG_EPHEMERAL],
        ]);
    }

    private function wantsEveryone(Request $request): bool
    {
        $option = collect($request->input('data.options', []))->firstWhere('name', self::OPTION_EVERYONE);

        return (bool) ($option['value'] ?? false);
    }

    private function syncEveryone(ConsentmentModel $account): JsonResponse
    {
        if (! in_array((string) $account->userVid, config('brauth.admin_vids'), true)) {
            return $this->reply(__('text.syncEveryoneNotAllowed'));
        }

        $this->sync->requestFullRun();

        Log::notice('Full sync requested', ['event' => 'sync.requested', 'vid' => $account->userVid]);

        return $this->reply(__('text.syncEveryoneQueued'));
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

    private function reply(string $content): JsonResponse
    {
        return response()->json([
            'type' => self::RESPONSE_MESSAGE,
            'data' => ['content' => $content, 'flags' => self::FLAG_EPHEMERAL],
        ]);
    }
}
