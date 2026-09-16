<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('discord-consentment', 'firstName')) {
            return;
        }

        // The timestamps of this table were created with a zero default, which MySQL
        // revalidates on every ALTER, so the check is lifted for this statement only
        $this->withoutStrictDates(function () {
            Schema::table('discord-consentment', function (Blueprint $table) {
                // IVAO only exposes the name of members who make it public, so it is kept from the login
                $table->string('firstName', 64)->nullable()->after('discordId');
            });
        });
    }

    public function down(): void
    {
        $this->withoutStrictDates(function () {
            Schema::table('discord-consentment', function (Blueprint $table) {
                $table->dropColumn('firstName');
            });
        });
    }

    private function withoutStrictDates(callable $callback): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $callback();

            return;
        }

        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            $callback();
        } finally {
            DB::statement("SET SESSION sql_mode = '".str_replace("'", '', $mode)."'");
        }
    }
};
