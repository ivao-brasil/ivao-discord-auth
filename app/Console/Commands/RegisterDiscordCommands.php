<?php

namespace App\Console\Commands;

use App\Infrastructure\Http\Controllers\DiscordInteractionController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegisterDiscordCommands extends Command
{
    protected $signature = 'discord:register-commands';

    protected $description = 'Register the slash commands of the bot on the Discord server';

    public function handle(): int
    {
        $applicationId = config('services.discord.client_id');
        $guildId = config('services.discord.guild_id');

        $response = Http::withToken(config('services.discord.bot_token'), 'Bot')
            ->acceptJson()
            ->put("https://discord.com/api/v10/applications/{$applicationId}/guilds/{$guildId}/commands", [
                [
                    'name' => 'sync',
                    'type' => 1,
                    'description' => __('text.syncCommandDescription'),
                    'options' => [
                        [
                            'name' => DiscordInteractionController::OPTION_EVERYONE,
                            'type' => 5,
                            'description' => __('text.syncEveryoneOptionDescription'),
                            'required' => false,
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            $this->error("Discord answered {$response->status()}: {$response->body()}");

            return self::FAILURE;
        }

        $this->info('Registered: '.collect($response->json())->pluck('name')->map(fn ($name) => "/{$name}")->join(', '));

        return self::SUCCESS;
    }
}
