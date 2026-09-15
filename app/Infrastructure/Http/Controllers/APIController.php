<?php

namespace App\Infrastructure\Http\Controllers;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\Guild;
use Illuminate\Http\Request;

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

    function saveRoles(Request $request){
        return $this->roleService->saveAllRoles($request->all());
    }

}
