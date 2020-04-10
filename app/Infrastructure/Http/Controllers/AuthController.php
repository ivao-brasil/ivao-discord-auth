<?php

namespace App\Infrastructure\Http\Controllers;

use App\Services\IVAOApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Socialite;
use App\Services\GuildService;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function Discord(){
        return Socialite::with('discord')->scopes(['guilds.join'])->redirect();
    }

    public function DiscordCallback(Request $request){
        $user = Socialite::driver('discord')->user();
        $request->session()->put('DISCORD_TOKEN', $user->token);
        $discordService = new GuildService($user->token);
        $discordService->joinInTheServer();

        $contents = Storage::disk('local')->get('roles');
        $data = Crypt::decryptString($contents);
        $rolesData = json_decode($data);

        $IVAOTOKEN = $request->session()->get('IVAOTOKEN');
        $IVAOAPI = new IVAOApiService($IVAOTOKEN);
        $IVAOAPI->getUserData();

        Log::info([
            'event' => 'join.server',
            'user' => $IVAOAPI->getVid()
        ]);

        try {
            if(count($IVAOAPI->getStaff()) > 0){
                $this->assignStaff($discordService, $IVAOAPI, $rolesData);
            }

            $this->assignMember($discordService, $IVAOAPI, $rolesData);
            $this->changeName($discordService, $IVAOAPI);

            return redirect('/success');
        }
        catch (Exception $e){

        }

    }

    private function changeName(GuildService $discordService, IVAOApiService $IVAOAPI){
        if(count($IVAOAPI->getStaff()) > 0){
            $nick = explode(" ",$IVAOAPI->getFirstName())[0]." | ";
            foreach($IVAOAPI->getStaff() as $staff){
                $nick .= " $staff";
            }
        }
        else {
            $nick = explode(" ",$IVAOAPI->getFirstName())[0]." - ".$IVAOAPI->getVid();
        }

        $discordService->changeName($nick);
        Log::info([
            'event' => 'name.changed',
            'user' => $IVAOAPI->getVid(),
            'role' => $nick
        ]);
    }

    private function assignMember(GuildService $discordService, IVAOApiService $IVAOAPI, $rolesData){
        try {
            $role = array_values(array_filter($rolesData, function($role) {
                return $role->sulfix === 'DiscordManager';
            }))[0];

            foreach($role->id as $id){
                $discordService->addRole($id);
                Log::info([
                    'event' => 'role.assignment',
                    'user' => $IVAOAPI->getVid(),
                    'role' => $role->id
                ]);
            }
        }
        catch(\Exception $e){

        }
    }

    private function assignStaff(GuildService $discordService, IVAOApiService $IVAOAPI, $rolesData) {
        $staffPos = $IVAOAPI->getStaff();
        foreach($staffPos as $staff){
            foreach($rolesData as $role){
                if(strstr($role->sulfix,$staff)){
                    foreach($role->id as $id){
                        $discordService->addRole($id);
                        Log::info([
                            'event' => 'role.assignment',
                            'user' => $IVAOAPI->getVid(),
                            'role' => $role->id
                        ]);
                    }
                }
            }
        }
    }
}
