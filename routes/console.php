<?php

use App\Application\Sync\MemberSyncService;
use App\Domain\Contracts\IVAOUserDirectoryContract;
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

// Mantém a lista de staff em cache para o /sync responder dentro dos 3 s do Discord
// Falhas da API da IVAO ficam fora do log, senão viram um erro por minuto
Schedule::call(fn () => rescue(fn () => app(IVAOUserDirectoryContract::class)->staffPositions(), report: false))
    ->name('ivao:staff-positions')
    ->everyMinute()
    ->withoutOverlapping();
