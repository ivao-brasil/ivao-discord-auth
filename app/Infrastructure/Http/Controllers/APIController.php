<?php

namespace App\Infrastructure\Http\Controllers;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Domain\Entities\Guild;
use Illuminate\Http\Request;
use App\Infrastructure\Services\DiscordGuildService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Collection;

class APIController extends Controller
{
    private $discordGuildService;
    private $roleService;

    /**
     * APIController constructor.
     * @param $discordGuildService
     */
    public function __construct(GuildServiceContract $discordGuildService, RolesServiceContract $roleService)
    {
        $this->discordGuildService = $discordGuildService;
        $this->roleService = $roleService;
    }

    function getDiscordRoles(Request $request){
        $guild = new Guild(env('DISCORD_GUILD_ID'));
        $roles = Collection::make($this->discordGuildService->getServerRoles($guild));
        return $roles->map(function($role, $key){
            $role->id = (string)$role->id;
            return $role;
        });
    }

    function getActualRoles(Request $request){
        return $this->roleService->getAllRoles();
    }

    function saveRoles(Request $request){
        return $this->roleService->saveAllRoles($request->all());
    }

}
