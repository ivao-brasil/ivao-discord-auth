<?php

namespace App\Infrastructure\Providers;

use App\Application\Contracts\DiscordIVAOAuthServiceInterface;
use App\Application\DiscordIVAOAuthService;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOApiServiceContract;
use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Domain\Contracts\RolesServiceContract;
use App\Infrastructure\Services\ConsentmentService;
use App\Infrastructure\Services\DiscordGuildService;
use App\Infrastructure\Services\IVAOApiService;
use App\Infrastructure\Services\IVAOUserDirectory;
use App\Infrastructure\Services\RolesService;
use App\Infrastructure\Socialite\IVAOProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IVAOApiServiceContract::class, IVAOApiService::class);

        $this->app->bind(DiscordIVAOAuthServiceInterface::class, DiscordIVAOAuthService::class);

        $this->app->bind(GuildServiceContract::class, function () {
            return new DiscordGuildService(
                config('services.discord.bot_token'),
                config('services.discord.guild_id'),
                config('services.discord.client_id')
            );
        });

        $this->app->bind(RolesServiceContract::class, RolesService::class);

        $this->app->bind(ConsentmentServiceContract::class, ConsentmentService::class);

        $this->app->bind(IVAOUserDirectoryContract::class, IVAOUserDirectory::class);
    }

    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, [DiscordExtendSocialite::class, 'handle']);

        $this->app->make(SocialiteFactory::class)->extend('ivao', function ($app) {
            $config = $app['config']['services.ivao'];

            return (new IVAOProvider(
                $app['request'],
                $config['client_id'],
                $config['client_secret'],
                $config['redirect']
            ))->setScopes(array_filter(explode(' ', $config['scopes'])));
        });
    }
}
