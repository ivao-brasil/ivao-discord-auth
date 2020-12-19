<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Consentment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discord-consentment', function (Blueprint $table) {
            $table->id();
            $table->string('userVid', 6);
            $table->string('discordId', 64);
            $table->string('nickName');
            $table->string('roles');
            $table->string('division', 2);
            $table->boolean('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discord-consentment');
    }
}
