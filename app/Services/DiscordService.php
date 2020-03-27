<?php

namespace App\Services;

use App\Services\Contracts\DiscordServiceContract;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use RestCord\DiscordClient;

class DiscordService implements  DiscordServiceContract {

    private $discordToken;
    private $guild;
    private $availableRoles;
    private $discordClient;
    public $user;

    public function __construct($discordToken){
        if($discordToken !== ''){
            $this->discordToken = $discordToken;
            $this->getUserData();
        }

        $this->discordClient = new DiscordClient((['token' => env('DISCORD_BOT_TOKEN')]));
        $this->guild = $this->discordClient->guild->getGuild(['guild.id' => (int)env('DISCORD_GUILD_ID')]);
        $this->availableRoles = $this->discordClient->guild->getGuildRoles(['guild.id' => (int)env('DISCORD_GUILD_ID')]);
    }

    public function joinInTheServer()
    {
        $this->discordClient->guild->addGuildMember([
            'guild.id' => (int)env('DISCORD_GUILD_ID'),
            'user.id' => (int)$this->user->id,
            'access_token' => $this->discordToken
        ]);
    }

    public function addRole($roleId){
        $this->discordClient->guild->addGuildMemberRole([
            'guild.id' => (int)env('DISCORD_GUILD_ID'),
            'user.id' => (int)$this->user->id,
            'role.id' => $roleId
        ]);
    }

    public function getRoles(){
        return $this->discordClient->guild->getGuildRoles([
            'guild.id' => (int)env('DISCORD_GUILD_ID')
        ]);
    }

    public function getUserData()
    {
        $this->user = Socialite::driver('discord')->userFromToken($this->discordToken);
    }

    public function changeName ($nickname){
        $this->discordClient->guild->modifyGuildMember([
            'guild.id' => (int)env('DISCORD_GUILD_ID'),
            'user.id' => (int)$this->user->id,
            'nick' => $nickname
        ]);
    }
}
