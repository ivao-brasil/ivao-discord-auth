<?php

namespace App\Infrastructure\Http\Controllers;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Entities\Guild;
use Illuminate\Http\Request;
use App\Infrastructure\Services\DiscordGuildService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Collection;

class APIController extends Controller
{
    private $discordGuildService;

    /**
     * APIController constructor.
     * @param $discordGuildService
     */
    public function __construct(GuildServiceContract $discordGuildService)
    {
        $this->discordGuildService = $discordGuildService;
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
        $contents = Storage::disk('local')->get('roles');
        $data = Crypt::decryptString($contents);
        return response()->json(json_decode($data));
    }

    function saveRoles(Request $request){
        $data = json_encode($request->all());
        $data = Crypt::encryptString($data);
        Storage::disk('local')->put('roles', $data);
    }

}
