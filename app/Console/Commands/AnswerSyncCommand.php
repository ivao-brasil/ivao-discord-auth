<?php

namespace App\Console\Commands;

use App\Application\Sync\SyncCommand;
use Illuminate\Console\Command;

class AnswerSyncCommand extends Command
{
    protected $signature = 'discord:answer-sync
        {discordId : Discord id of the member who ran /sync}
        {--todos : The member asked for every member to be synced}';

    protected $description = 'Answer a /sync received by the gateway bot, printing the reply';

    public function handle(SyncCommand $command): int
    {
        $discordId = (string) $this->argument('discordId');

        $this->line($command->answer($discordId, (bool) $this->option('todos')) ?? $command->run($discordId));

        return self::SUCCESS;
    }
}
