<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('discord-consentment', 'staffPositions')) {
            return;
        }

        $this->withoutStrictDates(function () {
            Schema::table('discord-consentment', function (Blueprint $table) {
                // Kept from the login, because IVAO hides the positions of private profiles
                $table->string('staffPositions', 512)->nullable()->after('firstName');
            });
        });
    }

    public function down(): void
    {
        $this->withoutStrictDates(function () {
            Schema::table('discord-consentment', function (Blueprint $table) {
                $table->dropColumn('staffPositions');
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
