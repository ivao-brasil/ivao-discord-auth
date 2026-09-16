<?php

namespace App\Console\Commands;

use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Domain\Contracts\RolesServiceContract;
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
        ConsentmentServiceContract $consentments
    ): int {
        $rules = $roles->rules();

        if ($rules->isEmpty()) {
            $this->error('No role rules are configured.');

            return self::FAILURE;
        }

        $catalogue = $directory->staffPositionCatalogue();
        $network = $directory->staffPositions();

        $covered = $rules->flatMap(fn (RoleRule $rule) => $rule->getStaff())->unique();

        // Only positions held by someone linked here can cost anyone a role
        $held = Collection::make($consentments->allActive())
            ->flatMap(fn (ConsentmentModel $account) => Collection::make($network[$account->userVid] ?? [])
                ->map(fn (array $position) => ['position' => $position['id'], 'vid' => $account->userVid]))
            ->groupBy('position')
            ->map(fn (Collection $entries) => $entries->pluck('vid')->unique()->values());

        $missing = $held->reject(fn (Collection $vids, string $position) => $covered->contains($position));

        $this->line(sprintf(
            '%d positions held by linked members, %d listed in the rules.',
            $held->count(),
            $held->keys()->intersect($covered)->count()
        ));

        if ($missing->isEmpty()) {
            $this->info('Every position held by a linked member is covered by a rule.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("These positions match no rule, so the sync would take the staff roles of who holds them:");
        $this->newLine();

        $additions = [];

        foreach ($missing as $position => $vids) {
            $department = $this->departmentOf($position, $catalogue);
            $candidates = $this->rulesForDepartment($rules, $department, $catalogue);

            $this->line(sprintf(
                '  %-10s %-34s %d member(s) %s',
                $position,
                $this->nameOf($position, $catalogue) ?: '(unknown position)',
                $vids->count(),
                $candidates->count() === 1
                    ? '-> rule "'.$candidates->first()->getName().'"'
                    : ($candidates->isEmpty() ? '-> no rule covers '.($department ?: 'this department') : '-> several rules, decide by hand')
            ));

            if ($candidates->count() === 1) {
                $additions[$candidates->first()->getId()][] = $position;
            }
        }

        $this->newLine();

        if (! $this->option('fix')) {
            $this->info('Run it again with --fix to add the positions that map to a single rule.');

            return self::SUCCESS;
        }

        if ($additions === []) {
            $this->warn('None of them maps to a single rule, so there is nothing to add automatically.');

            return self::SUCCESS;
        }

        $roles->saveAllRoles($rules->map(fn (RoleRule $rule) => array_replace($rule->toArray(), [
            'staff' => $rule->getStaff()->merge($additions[$rule->getId()] ?? [])->unique()->values()->all(),
        ]))->all());

        $added = array_sum(array_map('count', $additions));
        $this->info("Added {$added} position(s) to ".count($additions).' rule(s).');

        return self::SUCCESS;
    }

    /**
     * Rules that already list a position of the same department, which is where a new
     * position of that department belongs.
     *
     * @param  Collection<int, RoleRule>  $rules
     * @return Collection<int, RoleRule>
     */
    private function rulesForDepartment(Collection $rules, string $department, array $catalogue): Collection
    {
        if ($department === '') {
            return Collection::make();
        }

        return $rules->filter(fn (RoleRule $rule) => $rule->getStaff()
            ->contains(fn (string $position) => $this->departmentOf($position, $catalogue) === $department))
            ->values();
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
