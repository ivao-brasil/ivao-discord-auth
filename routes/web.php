<?php

use App\Infrastructure\Http\Controllers\Admin\AdminController;
use App\Infrastructure\Http\Controllers\Admin\DiscordRoleController;
use App\Infrastructure\Http\Controllers\Admin\MemberController;
use App\Infrastructure\Http\Controllers\Admin\RuleController;
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

Route::view('/success', 'success');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin', AdminController::class)->name('admin');

    Route::prefix('api/admin')->group(function () {
        Route::get('roles', DiscordRoleController::class);
        Route::get('rules', [RuleController::class, 'index']);
        Route::put('rules', [RuleController::class, 'update']);
        Route::post('sync', [MemberController::class, 'syncAll']);
        Route::get('members', [MemberController::class, 'index']);
        Route::get('members/{account}', [MemberController::class, 'show']);
        Route::post('members/{account}/sync', [MemberController::class, 'sync']);
        Route::delete('members/{account}', [MemberController::class, 'destroy']);
    });
});

Route::middleware('auth')->group(function () {
    Route::view('/revoke', 'revoke')->name('revoke');
    Route::post('/revoke', [MainController::class, 'revoke']);
});
