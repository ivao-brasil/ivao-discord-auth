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

        // Minimum interval between /sync commands from the same member
        'cooldown_minutes' => (int) env('SYNC_COOLDOWN_MINUTES', 5),
    ],

    // Underscores are rendered as spaces
    'title' => str_replace('_', ' ', (string) env('DEFAULT_TITLE', 'IVAO Discord Auth')),

];
