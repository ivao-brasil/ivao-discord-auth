<?php

use App\Infrastructure\Http\Controllers\APIController;
use App\Infrastructure\Http\Controllers\DiscordController;
use App\Infrastructure\Http\Controllers\DiscordInteractionController;
use App\Infrastructure\Http\Controllers\IVAOController;
use App\Infrastructure\Http\Controllers\MainController;
use App\Infrastructure\Http\Middleware\VerifyDiscordSignature;
use Illuminate\Support\Facades\Route;

Route::get('/', [MainController::class, 'showIndex'])->middleware('auth')->name('home');
Route::get('/ivao/login', [IVAOController::class, 'login'])->name('login');
Route::get('/ivao/callback', [IVAOController::class, 'loginCallback']);
Route::get('/discord/login', [DiscordController::class, 'login'])->middleware('auth')->name('auth/discord');
Route::get('/discord/callback', [DiscordController::class, 'loginCallback'])->middleware('auth');
Route::post('/discord/interactions', DiscordInteractionController::class)->middleware(VerifyDiscordSignature::class);

Route::view('/admin', 'admin')->middleware(['auth', 'admin']);
Route::view('/success', 'success');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/api/discord/roles', [APIController::class, 'getDiscordRoles']);
    Route::get('/api/discord/actualRoles', [APIController::class, 'getActualRoles']);
    Route::post('/api/discord/saveRoles', [APIController::class, 'saveRoles']);
});

Route::middleware('auth')->group(function () {
    Route::view('/revoke', 'revoke')->name('revoke');
    Route::post('/revoke', [MainController::class, 'revoke']);
});
