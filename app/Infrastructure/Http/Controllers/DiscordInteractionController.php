<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Sync\SyncCommand;
use App\Domain\Contracts\GuildServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordInteractionController extends Controller
{
    private const TYPE_PING = 1;
    private const TYPE_APPLICATION_COMMAND = 2;

    private const RESPONSE_PONG = 1;
    private const RESPONSE_MESSAGE = 4;
    private const RESPONSE_DEFERRED_MESSAGE = 5;

    private const FLAG_EPHEMERAL = 64;

    public const OPTION_EVERYONE = 'todos';

    private $syncCommand;
    private $guildService;

    public function __construct(SyncCommand $syncCommand, GuildServiceContract $guildService)
    {
        $this->syncCommand = $syncCommand;
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

        $answer = $this->syncCommand->answer($discordId, $this->wantsEveryone($request));
        if ($answer !== null) {
            return $this->reply($answer);
        }

        $token = (string) $request->input('token');

        // Discord expects an answer within 3 seconds, so the sync runs after the deferred response is sent
        defer(function () use ($discordId, $token) {
            $this->guildService->editInteractionResponse($token, $this->syncCommand->run($discordId));
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

    private function reply(string $content): JsonResponse
    {
        return response()->json([
            'type' => self::RESPONSE_MESSAGE,
            'data' => ['content' => $content, 'flags' => self::FLAG_EPHEMERAL],
        ]);
    }
}
