<?php

namespace App\Console\Commands;

use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use App\Domain\Contracts\GuildServiceContract;
use App\Domain\Contracts\IVAOUserDirectoryContract;
use App\Domain\Entities\Guild;
use App\Domain\Entities\Member;
use Illuminate\Console\Command;

class BackfillMemberNames extends Command
{
    protected $signature = 'discord:backfill-names {--dry-run : List the names without saving them}';

    protected $description = 'Fill the stored name and staff positions of members linked before they were kept';

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
            if (! empty($account->firstName) && ! empty($account->staffPositions)) {
                continue;
            }

            $user = $this->fromIVAO($directory, $account);
            $nickname = $guildService->getMember($account->discordId, $guild)['nick'] ?? $account->nickName;

            $changes = array_filter([
                'firstName' => $account->firstName ?: ($this->nameOf($user) ?? $this->nameFromNickname($nickname)),
                'staffPositions' => $account->staffPositions ?: ($this->positionsOf($user) ?? $this->positionsFromNickname($nickname)),
            ]);

            if (! isset($changes['firstName'])) {
                $missing++;

                continue;
            }

            $this->line("{$account->userVid}: {$changes['firstName']}".
                (isset($changes['staffPositions']) ? " ({$changes['staffPositions']})" : ''));

            $dryRun || $account->update($changes);
            $filled++;
        }

        $this->info($dryRun
            ? "Would fill {$filled}, no name found for {$missing}."
            : "Filled {$filled}, no name found for {$missing}.");

        return self::SUCCESS;
    }

    private function fromIVAO(IVAOUserDirectoryContract $directory, ConsentmentModel $account): ?Member
    {
        try {
            $user = $directory->find($account->userVid);
        } catch (\Throwable $e) {
            return null;
        }

        return $user ? new Member($user) : null;
    }

    private function nameOf(?Member $member): ?string
    {
        return $member ? $this->clean(explode(' ', trim((string) $member->getFirstName()))[0]) : null;
    }

    private function positionsOf(?Member $member): ?string
    {
        if ($member === null || $member->hasHiddenProfile()) {
            return null;
        }

        return $member->getStaffTitles()->join(':') ?: null;
    }

    /**
     * Nicknames are "Name - VID" for members and "Name | BR-XX IVAO-YY" for staff.
     */
    private function nameFromNickname(?string $nickname): ?string
    {
        return $this->clean(trim(explode('|', explode(' - ', (string) $nickname)[0])[0]));
    }

    private function positionsFromNickname(?string $nickname): ?string
    {
        $parts = explode('|', (string) $nickname);

        if (count($parts) < 2) {
            return null;
        }

        return implode(':', preg_split('/\s+/', trim($parts[1]), -1, PREG_SPLIT_NO_EMPTY)) ?: null;
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
