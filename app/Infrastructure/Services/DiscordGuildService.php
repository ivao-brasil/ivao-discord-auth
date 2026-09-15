<?php


namespace App\Infrastructure\Services;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;
use App\Domain\Entities\Roles;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DiscordGuildService implements GuildServiceContract
{
    private const API_URL = 'https://discord.com/api/v10';

    private $botToken;
    private $guildId;
    private $serverRoles;

    public function __construct($botToken, $guildId)
    {
        $this->botToken = $botToken;
        $this->guildId = $guildId;
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

        $member->getRoles()->each(function ($role) use ($member, $guild) {
            $this->addRoleToMember($member, $role, $guild);
        });

        $this->changeMemberNickname($member, $guild);
    }

    private function addRoleToMember(Member $member, Roles $role, Guild $guild)
    {
        $this->discord()->put("/guilds/{$guild->getId()}/members/{$member->getDiscordId()}/roles/{$role->getId()}");
    }

    private function changeMemberNickname(Member $member, Guild $guild)
    {
        $this->discord()->patch("/guilds/{$guild->getId()}/members/{$member->getDiscordId()}", [
            'nick' => $member->generateNickname(),
        ]);
    }

    public function getServerRoles(Guild $guild)
    {
        return $this->serverRoles ??= $this->discord()->get("/guilds/{$guild->getId()}/roles")->json();
    }

    public function getRolename(Guild $guild, Roles $role)
    {
        return Collection::make($this->getServerRoles($guild))->firstWhere('id', (string) $role->getId())['name'] ?? null;
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
