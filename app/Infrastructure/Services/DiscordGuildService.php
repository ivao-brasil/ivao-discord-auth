<?php


namespace App\Infrastructure\Services;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordGuildService implements GuildServiceContract
{
    private const API_URL = 'https://discord.com/api/v10';

    private $botToken;
    private $guildId;
    private $applicationId;
    private $serverRoles;

    public function __construct($botToken, $guildId, $applicationId = null)
    {
        $this->botToken = $botToken;
        $this->guildId = $guildId;
        $this->applicationId = $applicationId;
    }

    public function getGuildId()
    {
        return $this->guildId;
    }

    public function addMember(Member $member, Guild $guild)
    {
        $this->discord()->put("/guilds/{$guild->getId()}/members/{$member->getDiscordId()}", [
            'access_token' => $member->getDiscordAccessToken(),
        ]);

        $member->getRoles()->each(function (string $roleId) use ($member, $guild) {
            $this->addRole($member->getDiscordId(), $roleId, $guild);
        });

        if ($nickname = $member->generateNickname()) {
            $this->setNickname($member->getDiscordId(), $nickname, $guild);
        }
    }

    public function getMember(string $discordId, Guild $guild): ?array
    {
        try {
            return $this->discord()->get("/guilds/{$guild->getId()}/members/{$discordId}")->json();
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return null;
            }

            throw $e;
        }
    }

    public function addRole(string $discordId, string $roleId, Guild $guild): bool
    {
        return $this->unlessAboveBot($discordId, "add role {$roleId}", function () use ($discordId, $roleId, $guild) {
            $this->discord()->send('PUT', "/guilds/{$guild->getId()}/members/{$discordId}/roles/{$roleId}");
        });
    }

    public function removeRole(string $discordId, string $roleId, Guild $guild): bool
    {
        return $this->unlessAboveBot($discordId, "remove role {$roleId}", function () use ($discordId, $roleId, $guild) {
            $this->discord()->delete("/guilds/{$guild->getId()}/members/{$discordId}/roles/{$roleId}");
        });
    }

    public function setNickname(string $discordId, string $nickname, Guild $guild): bool
    {
        return $this->unlessAboveBot($discordId, 'nickname', function () use ($discordId, $nickname, $guild) {
            $this->discord()->patch("/guilds/{$guild->getId()}/members/{$discordId}", [
                'nick' => $nickname,
            ]);
        });
    }

    public function getServerRoles(Guild $guild)
    {
        return $this->serverRoles ??= $this->discord()->get("/guilds/{$guild->getId()}/roles")->json();
    }

    public function getRolename(Guild $guild, string $roleId)
    {
        return Collection::make($this->getServerRoles($guild))->firstWhere('id', $roleId)['name'] ?? null;
    }

    public function removeFromServer($discordId, Guild $guild)
    {
        try {
            return $this->discord()->delete("/guilds/{$guild->getId()}/members/{$discordId}");
        } catch (RequestException $e) {
            // Member already left the server
            if ($e->response->status() === 404) {
                return null;
            }

            throw $e;
        }
    }

    public function editInteractionResponse(string $interactionToken, string $content): void
    {
        Http::baseUrl(self::API_URL)
            ->acceptJson()
            ->patch("/webhooks/{$this->applicationId}/{$interactionToken}/messages/@original", [
                'content' => $content,
            ])
            ->throw();
    }

    // Discord refuses changes to members whose top role is above the bot's (and to the server owner)
    private function unlessAboveBot(string $discordId, string $change, callable $callback): bool
    {
        try {
            $callback();

            return true;
        } catch (RequestException $e) {
            if ($e->response->status() !== 403) {
                throw $e;
            }

            Log::info([
                'event' => 'discord.missing.permissions',
                'discordId' => $discordId,
                'change' => $change,
            ]);

            return false;
        }
    }

    private function discord(): PendingRequest
    {
        return Http::baseUrl(self::API_URL)
            ->withToken($this->botToken, 'Bot')
            ->acceptJson()
            // Retry once on rate limit, waiting Discord's retry_after
            ->retry(2, function (int $attempt, \Exception $e) {
                return $e instanceof RequestException
                    ? (int) ceil(($e->response->json('retry_after') ?? 1) * 1000)
                    : 1000;
            }, function (\Exception $e) {
                return $e instanceof RequestException && $e->response->status() === 429;
            })
            ->throw();
    }
}
