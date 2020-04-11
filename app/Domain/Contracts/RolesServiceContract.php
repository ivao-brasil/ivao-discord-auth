<?php

namespace App\Domain\Contracts;

interface RolesServiceContract {
    public function getAllRoles();
    public function saveAllRoles($rolesData);
}
