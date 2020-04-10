<?php

namespace App\Infrastructure\Providers;

use App\Application\Contracts\DiscordIVAOAuthServiceInterface;
use App\Application\DiscordIVAOAuthService;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Infrastructure\Services\DiscordGuildService;
use App\Infrastructure\Services\IVAOApiService;
use App\Infrastructure\Services\RolesService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use App\Domain\Entities\Member;
use RestCord\DiscordClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(IVAOApiServiceContract::class, function($app) {
           return new IVAOApiService(new Http());
        });

        $this->app->bind(DiscordIVAOAuthServiceInterface::class, DiscordIVAOAuthService::class);

        $this->app->bind(GuildServiceContract::class, function($app) {
            return new DiscordGuildService(new DiscordClient(['token' => env('DISCORD_BOT_TOKEN')]), env('DISCORD_GUILD_ID'));
        });

        $this->app->bind(RolesServiceContract::class, RolesService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
