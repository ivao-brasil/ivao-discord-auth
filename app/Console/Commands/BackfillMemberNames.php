<?php

namespace App\Console\Commands;

use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Domain\Entities\Guild;
use Illuminate\Console\Command;

class BackfillMemberNames extends Command
{
    protected $signature = 'discord:backfill-names {--dry-run : List the names without saving them}';

    protected $description = 'Fill the stored first name of members linked before it was kept';

    public function handle(
        ConsentmentServiceContract $consentments,
        GuildServiceContract $guildService,
        IVAOUserDirectoryContract $directory
    ): int {
        $guild = Guild::FromService($guildService);
        $dryRun = (bool) $this->option('dry-run');
        $filled = 0;
        $missing = 0;

        foreach ($consentments->allActive() as $account) {
            if (! empty($account->firstName)) {
                continue;
            }

            $firstName = $this->fromIVAO($directory, $account)
                ?? $this->fromNickname($guildService->getMember($account->discordId, $guild)['nick'] ?? null)
                ?? $this->fromNickname($account->nickName);

            if ($firstName === null) {
                $missing++;

                continue;
            }

            $this->line("{$account->userVid}: {$firstName}");
            $dryRun || $account->update(['firstName' => $firstName]);
            $filled++;
        }

        $this->info($dryRun
            ? "Would fill {$filled}, no name found for {$missing}."
            : "Filled {$filled}, no name found for {$missing}.");

        return self::SUCCESS;
    }

    private function fromIVAO(IVAOUserDirectoryContract $directory, ConsentmentModel $account): ?string
    {
        try {
            $user = $directory->find($account->userVid);
        } catch (\Throwable $e) {
            return null;
        }

        return $this->clean(explode(' ', trim((string) ($user['firstName'] ?? '')))[0]);
    }

    /**
     * Nicknames are "Name - VID" for members and "Name | BR-XX" for staff.
     */
    private function fromNickname(?string $nickname): ?string
    {
        return $this->clean(trim(explode('|', explode(' - ', (string) $nickname)[0])[0]));
    }

    /**
     * Nicknames left by the runs that lost the name are only a dash and the VID.
     */
    private function clean(string $name): ?string
    {
        $name = trim($name, " -\t\n\r");

        return $name === '' || ctype_digit($name) ? null : mb_substr($name, 0, 64);
    }
}
