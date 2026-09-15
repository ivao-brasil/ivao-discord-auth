<?php

namespace App\Infrastructure\Services;

use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\RoleRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class RolesService implements RolesServiceContract
{
    private const FILE = 'roles';

    public function getAllRoles()
    {
        if (! Storage::disk('local')->exists(self::FILE)) {
            return [];
        }

        $data = Crypt::decryptString(Storage::disk('local')->get(self::FILE));

        return json_decode($data);
    }

    public function rules(): Collection
    {
        return RoleRule::collection(json_decode(json_encode($this->getAllRoles()), true) ?? []);
    }

    public function saveAllRoles($rolesData)
    {
        $data = json_encode($rolesData);
        $data = Crypt::encryptString($data);
        Storage::disk('local')->put(self::FILE, $data);
    }
}
