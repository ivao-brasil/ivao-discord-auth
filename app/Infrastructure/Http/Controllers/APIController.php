<?php

namespace App\Infrastructure\Http\Controllers;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\Guild;
use App\Domain\Contracts\IVAOApiServiceContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class APIController extends Controller
{
    private $discordGuildService;
    private $roleService;

    public function __construct(GuildServiceContract $discordGuildService, RolesServiceContract $roleService)
    {
        $this->discordGuildService = $discordGuildService;
        $this->roleService = $roleService;
    }

    function getDiscordRoles(Request $request){
        $guild = Guild::FromService($this->discordGuildService);
        return $this->discordGuildService->getServerRoles($guild);
    }

    function getActualRoles(Request $request){
        return $this->roleService->getAllRoles();
    }

    function saveRoles(Request $request, IVAOApiServiceContract $IVAOAPI){
        $this->roleService->saveAllRoles($request->all());

        Log::notice('Role rules updated', [
            'event' => 'roles.updated',
            'admin' => $IVAOAPI->getUserData()['id'],
            'rules' => count($request->all()),
        ]);
    }

}
