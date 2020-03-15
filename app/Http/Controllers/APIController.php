<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\DiscordService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class APIController extends Controller
{
    function getDiscordRoles(Request $request){
        $discordService = new DiscordService('');
        $roles = $discordService->getRoles();
        for($i=0; $i<count($roles); $i++){
            $roles[$i]->id = (string)$roles[$i]->id;
        }
        return $roles;
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
