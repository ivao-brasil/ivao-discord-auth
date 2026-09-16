<?php

namespace App\Console\Commands;

use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\Guild;
use App\Domain\Entities\RoleRule;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * IVAO creates staff positions over time, and a rule that lists them one by one goes out of
 * date without anyone noticing: the holder stops matching any rule and the sync takes their
 * staff roles away. This reports those positions before a run does that.
 */
class AuditRoleRules extends Command
{
    protected $signature = 'discord:audit-rules
        {--fix : Add each missing position to the rule that already covers its department}';

    protected $description = 'Report staff positions held by linked members that no role rule covers';

    public function handle(
        RolesServiceContract $roles,
        IVAOUserDirectoryContract $directory,
        ConsentmentServiceContract $consentments,
        GuildServiceContract $guildService
    ): int {
        $rules = $roles->rules();

        if ($rules->isEmpty()) {
            $this->error('No role rules are configured.');

            return self::FAILURE;
        }

        // Rules written in the old format carry no name, so they are shown by their roles
        $roleNames = Collection::make($guildService->getServerRoles(Guild::FromService($guildService)))
            ->pluck('name', 'id')
            ->all();

        $catalogue = $directory->staffPositionCatalogue();
        $network = $directory->staffPositions();

        $covered = $rules->flatMap(fn (RoleRule $rule) => $rule->getStaff())->unique();

        // Members of the guild hold positions all over the network; only the prefixes the
        // rules already mention belong to this division, and a Polish coordinator is not
        // missing from a rule written for Brazilian ones
        $scope = $covered->map(fn (string $position) => self::prefixOf($position))->unique();

        // Only positions held by someone linked here can cost anyone a role
        $held = Collection::make($consentments->allActive())
            ->flatMap(fn (ConsentmentModel $account) => Collection::make($network[$account->userVid] ?? [])
                ->map(fn (array $position) => ['position' => $position['id'], 'vid' => $account->userVid]))
            ->groupBy('position')
            ->map(fn (Collection $entries) => $entries->pluck('vid')->unique()->values());

        $inScope = $held->filter(fn (Collection $vids, string $position) => $scope->contains(self::prefixOf($position)));
        $missing = $inScope->reject(fn (Collection $vids, string $position) => $covered->contains($position));

        $this->line(sprintf(
            '%d positions held by linked members, %d of this division, %d listed in the rules.',
            $held->count(),
            $inScope->count(),
            $inScope->keys()->intersect($covered)->count()
        ));

        if ($missing->isEmpty()) {
            $this->info('Every position held by a linked member is covered by a rule.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("These positions match no rule, so the sync would take the staff roles of who holds them:");
        $this->newLine();

        $additions = [];
        $byHand = 0;

        foreach ($missing as $position => $vids) {
            $department = $this->departmentOf($position, $catalogue);
            $candidates = $this->rulesFor($rules, $position, $department, $catalogue);

            $described = $candidates->map(function (RoleRule $rule) use ($position, $catalogue, $roleNames, &$additions, &$byHand) {
                $label = '"'.$this->label($rule, $roleNames).'"';

                // A rule listing only the coordinators of a department is not where an
                // advisor of that department belongs, so only the same team is applied
                if (! $this->listsTeamOf($rule, $position, $catalogue)) {
                    $byHand++;

                    return $label.' (department only)';
                }

                $additions[$rule->getId()][] = $position;

                return $label;
            });

            $this->line(sprintf(
                '  %-10s %-38s %d member(s) -> %s',
                $position,
                $this->nameOf($position, $catalogue) ?: '(unknown position)',
                $vids->count(),
                $described->isEmpty()
                    ? 'no rule covers '.($department ?: 'this department')
                    : $described->join(', ')
            ));
        }

        $this->newLine();

        if ($byHand > 0) {
            $this->warn('A rule marked "department only" lists no position of the same team, such as a rule');
            $this->warn('for coordinators against an advisor position. Add those in /admin if they apply.');
            $this->newLine();
        }

        if (! $this->option('fix')) {
            $this->info('Run it again with --fix to add each position to the rules of its own team.');

            return self::SUCCESS;
        }

        if ($additions === []) {
            $this->warn('No rule lists a position of the same team, so there is nothing to add safely.');

            return self::SUCCESS;
        }

        $roles->saveAllRoles($rules->map(fn (RoleRule $rule) => array_replace($rule->toArray(), [
            'staff' => $rule->getStaff()->merge($additions[$rule->getId()] ?? [])->unique()->values()->all(),
        ]))->all());

        $added = array_sum(array_map('count', $additions));
        $this->info("Added {$added} position(s) to ".count($additions).' rule(s).');

        if ($byHand > 0) {
            $this->info("Left {$byHand} rule match(es) for you to decide in /admin.");
        }

        return self::SUCCESS;
    }

    /**
     * Whether the rule already lists a position of the same division and team, which is
     * what tells a coordinators rule apart from one that takes advisors too.
     */
    private function listsTeamOf(RoleRule $rule, string $position, array $catalogue): bool
    {
        $prefix = self::prefixOf($position);
        $team = $this->teamOf($position, $catalogue);

        return $team !== '' && $rule->getStaff()->contains(
            fn (string $listed) => self::prefixOf($listed) === $prefix && $this->teamOf($listed, $catalogue) === $team
        );
    }

    private function teamOf(string $position, array $catalogue): string
    {
        return $catalogue[self::catalogueCode($position, $catalogue)]['team'] ?? '';
    }

    /**
     * @param  array<string, string>  $roleNames
     */
    private function label(RoleRule $rule, array $roleNames): string
    {
        return $rule->getName() !== ''
            ? $rule->getName()
            : $rule->getRoles()->map(fn (string $role) => $roleNames[$role] ?? $role)->join(' + ');
    }

    /**
     * Rules that already list a position of the same division and department, which is
     * where a newly created position of that department belongs.
     *
     * @param  Collection<int, RoleRule>  $rules
     * @return Collection<int, RoleRule>
     */
    private function rulesFor(Collection $rules, string $position, string $department, array $catalogue): Collection
    {
        if ($department === '') {
            return Collection::make();
        }

        $prefix = self::prefixOf($position);

        return $rules->filter(fn (RoleRule $rule) => $rule->getStaff()->contains(
            fn (string $listed) => self::prefixOf($listed) === $prefix
                && $this->departmentOf($listed, $catalogue) === $department
        ))->values();
    }

    /**
     * The division or FIR a position belongs to; HQ positions such as WD6 carry none.
     */
    private static function prefixOf(string $position): string
    {
        $dash = strpos($position, '-');

        return $dash === false ? '' : substr($position, 0, $dash);
    }

    private function departmentOf(string $position, array $catalogue): string
    {
        return $catalogue[self::catalogueCode($position, $catalogue)]['department'] ?? '';
    }

    private function nameOf(string $position, array $catalogue): string
    {
        return $catalogue[self::catalogueCode($position, $catalogue)]['name'] ?? '';
    }

    /**
     * Division positions carry a prefix the catalogue does not use: BR-SOA2 is listed as
     * -SOA2, while HQ positions such as WD6 are listed as they are written.
     */
    private static function catalogueCode(string $position, array $catalogue): string
    {
        if (array_key_exists($position, $catalogue)) {
            return $position;
        }

        $dash = strpos($position, '-');

        return $dash === false ? $position : substr($position, $dash);
    }
}
