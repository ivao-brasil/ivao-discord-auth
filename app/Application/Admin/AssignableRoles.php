<?php

namespace App\Application\Admin;

use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Entities\Guild;
use Illuminate\Support\Collection;

/**
 * Discord roles that role rules may hand out.
 */
class AssignableRoles
{
    public const REASON_MANAGED = 'managed';
    public const REASON_ADMINISTRATOR = 'administrator';

    private const ADMINISTRATOR_PERMISSION = 0x8;

    private $guildService;

    public function __construct(GuildServiceContract $guildService)
    {
        $this->guildService = $guildService;
    }

    /**
     * Server roles from the highest position down, without @everyone.
     *
     * @return Collection<int, array{id: string, name: string, color: ?string, assignable: bool, reason: ?string, aboveBot: bool}>
     */
    public function all(): Collection
    {
        $guild = Guild::FromService($this->guildService);
        $roles = Collection::make($this->guildService->getServerRoles($guild));

        $bot = $this->guildService->getMember((string) config('services.discord.client_id'), $guild);
        $botPosition = $roles->whereIn('id', $bot['roles'] ?? [])->max('position') ?? 0;

        return $roles
            ->reject(fn (array $role) => $role['id'] === (string) $guild->getId())
            ->sortByDesc('position')
            ->values()
            ->map(fn (array $role) => [
                'id' => $role['id'],
                'name' => $role['name'],
                'color' => $role['color'] ? sprintf('#%06X', $role['color']) : null,
                'assignable' => $this->refusalReason($role) === null,
                'reason' => $this->refusalReason($role),
                'aboveBot' => $role['position'] >= $botPosition,
            ]);
    }

    /**
     * @param  string[]  $roleIds
     * @return string[] ids that do not exist or cannot be assigned
     */
    public function refused(array $roleIds): array
    {
        $assignable = $this->all()->where('assignable', true)->pluck('id');

        return array_values(array_diff($roleIds, $assignable->all()));
    }

    private function refusalReason(array $role): ?string
    {
        if ($role['managed'] ?? false) {
            return self::REASON_MANAGED;
        }

        return ((int) $role['permissions'] & self::ADMINISTRATOR_PERMISSION) ? self::REASON_ADMINISTRATOR : null;
    }
}
