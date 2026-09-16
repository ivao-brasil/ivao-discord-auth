<?php

use App\Application\Sync\MemberSyncService;
use Illuminate\Support\Facades\Schedule;

Schedule::command('discord:sync')
    ->dailyAt(config('brauth.sync.time'))
    ->timezone(config('brauth.sync.timezone'))
    ->withoutOverlapping();

// Full sync asked for from the /sync command or the admin page
Schedule::command('discord:sync')
    ->everyMinute()
    ->when(fn () => app(MemberSyncService::class)->fullRunWasRequested())
    ->withoutOverlapping();
