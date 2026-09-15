<?php

namespace App\Domain\Contracts;

use App\Domain\Entities\RoleRule;
use Illuminate\Support\Collection;

interface RolesServiceContract {
    public function getAllRoles();
    public function saveAllRoles($rolesData);

    /** @return Collection<int, RoleRule> */
    public function rules(): Collection;
}
