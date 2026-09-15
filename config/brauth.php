<?php

return [

    // VIDs allowed to manage role rules at /admin, separated by ":"
    'admin_vids' => array_values(array_filter(explode(':', (string) env('ADMIN_VIDS', '')))),

    // Members need more than this many pilot + ATC hours to join
    'min_hours' => (float) env('MIN_HOURS', 5),

    // Underscores are rendered as spaces
    'title' => str_replace('_', ' ', (string) env('DEFAULT_TITLE', 'IVAO Discord Auth')),

];
