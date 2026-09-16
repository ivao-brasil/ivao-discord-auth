<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discord-consentment', function (Blueprint $table) {
            // IVAO only exposes the name of members who make it public, so it is kept from the login
            $table->string('firstName', 64)->nullable()->after('discordId');
        });
    }

    public function down(): void
    {
        Schema::table('discord-consentment', function (Blueprint $table) {
            $table->dropColumn('firstName');
        });
    }
};
