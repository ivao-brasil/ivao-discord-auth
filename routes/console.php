<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('discord:sync')
    ->dailyAt(config('brauth.sync.time'))
    ->timezone(config('brauth.sync.timezone'))
    ->withoutOverlapping();
