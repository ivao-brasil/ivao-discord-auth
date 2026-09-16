<?php

namespace App\Application;

use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\Member;
use App\Domain\Entities\RoleRule;
use Illuminate\Support\Collection;

class RoleResolver
{
    private $rolesService;
    private $rules;

    public function __construct(RolesServiceContract $rolesService)
    {
        $this->rolesService = $rolesService;
    }

    public function hasEnoughHours(Member $member): bool
    {
        return $member->getTotalHours() > config('brauth.min_hours');
    }

    /**
     * Whether the member still qualifies for the roles the rules give.
     *
     * Only a suspended account is proof that the roles are no longer due: inactivity on
     * IVAO is common and reversible, and costing a member their access over it would
     * shut out exactly the people who come back.
     */
    public function isEligible(Member $member): bool
    {
        return ! $member->isSuspended() && $this->hasEnoughHours($member);
    }

    /**
     * Discord role ids the member qualifies for, ignoring account status and minimum hours.
     *
     * @return Collection<int, string>
     */
    public function rolesFor(Member $member): Collection
    {
        return $this->rules()
            ->filter(fn (RoleRule $rule) => $rule->matches($member))
            ->flatMap(fn (RoleRule $rule) => $rule->getRoles())
            ->unique()
            ->values();
    }

    /**
     * Roles controlled by the rules; any other role on the server is never changed.
     *
     * @return Collection<int, string>
     */
    public function managedRoles(): Collection
    {
        return $this->rules()
            ->flatMap(fn (RoleRule $rule) => $rule->getRoles())
            ->unique()
            ->values();
    }

    /**
     * Roles given by rules that depend on staff positions, which a hidden profile cannot confirm.
     *
     * @return Collection<int, string>
     */
    public function staffRoles(): Collection
    {
        return $this->rules()
            ->filter(fn (RoleRule $rule) => $rule->getStaff()->isNotEmpty())
            ->flatMap(fn (RoleRule $rule) => $rule->getRoles())
            ->unique()
            ->values();
    }

    /** @return Collection<int, RoleRule> */
    private function rules(): Collection
    {
        return $this->rules ??= $this->rolesService->rules();
    }
}
