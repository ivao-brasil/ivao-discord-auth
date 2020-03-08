<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Socialite;

class AuthController extends Controller
{
    public function Discord(){
        return Socialite::with('discord')->scopes(['guilds.join'])->redirect();
    }

    public function DiscordCallback(Request $request){
        $user = Socialite::driver('discord')->user();
        $request->session()->put('DISCORD_TOKEN', $user->token);
        return redirect('/success');
    }
}
