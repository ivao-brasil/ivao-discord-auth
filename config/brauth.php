<?php

return [

    // VIDs allowed to manage role rules at /admin, separated by ":"
    'admin_vids' => array_values(array_filter(explode(':', (string) env('ADMIN_VIDS', '')))),

    // Members need more than this many pilot + ATC hours to join
    'min_hours' => (float) env('MIN_HOURS', 5),

    'sync' => [
        // Daily run of discord:sync, via "php artisan schedule:run"
        'time' => env('SYNC_TIME', '04:00'),
        'timezone' => env('SYNC_TIMEZONE', 'America/Sao_Paulo'),

        // Pause between members to stay within IVAO and Discord rate limits
        'delay_ms' => (int) env('SYNC_DELAY_MS', 250),

        // A run that takes roles from more members than this stops, because such a number
        // means the data it is acting on cannot be trusted
        'max_removals' => (int) env('SYNC_MAX_REMOVALS', 50),

        // Minimum interval between /sync commands from the same member
        'cooldown_minutes' => (int) env('SYNC_COOLDOWN_MINUTES', 5),
    ],

    // IVAO rating ids, shown as minimum requirements in role rules
    'ratings' => [
        'atc' => [2 => 'AS1', 3 => 'AS2', 4 => 'AS3', 5 => 'ADC', 6 => 'APC', 7 => 'ACC', 8 => 'SEC', 9 => 'SAI', 10 => 'CAI'],
        'pilot' => [2 => 'FS1', 3 => 'FS2', 4 => 'FS3', 5 => 'PP', 6 => 'SPP', 7 => 'CP', 8 => 'ATP', 9 => 'SFI', 10 => 'CFI'],
    ],

    // Underscores are rendered as spaces
    'title' => str_replace('_', ' ', (string) env('DEFAULT_TITLE', 'IVAO Discord Auth')),

];
