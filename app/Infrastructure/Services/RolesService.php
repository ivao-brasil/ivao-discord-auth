<?php

namespace App\Infrastructure\Services;

use App\Domain\Contracts\RolesServiceContract;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class RolesService implements RolesServiceContract {

    public function getAllRoles()
    {
        try {
            $content = Storage::disk('local')->get('roles');
            $data = Crypt::decryptString($content);
            return json_decode($data);
        } catch (FileNotFoundException $e) {
            return json_decode("[]");
        }
    }
}
