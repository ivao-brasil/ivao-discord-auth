<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConsentmentModel extends Model
{
    protected $table = 'discord-consentment';

    protected $fillable = [
        "userVid",
        "discordId",
        "nickName",
        "roles",
        "division",
        "status"
    ];
}
