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
        $networkPositions = $directory->staffPositions();
        $filled = 0;
        $missing = 0;

        foreach ($consentments->allActive() as $account) {
            // Positions come from the network list, never from a nickname, which can
            // carry a position the member left long ago
            $positions = $this->positionsOf($networkPositions[$account->userVid] ?? []);
            $firstName = $account->firstName;

            if (empty($firstName)) {
                $user = $this->fromIVAO($directory, $account);
                $nickname = $guildService->getMember($account->discordId, $guild)['nick'] ?? $account->nickName;
                $firstName = $this->nameOf($user) ?? $this->nameFromNickname($nickname);
            }

            $changes = array_filter([
                'firstName' => $firstName === $account->firstName ? null : $firstName,
                'staffPositions' => $positions === $account->staffPositions ? null : $positions,
            ]);

            if ($firstName === null) {
                $missing++;
            }

            if ($changes === []) {
                continue;
            }

            $this->line("{$account->userVid}: ".($firstName ?? '—').($positions ? " ({$positions})" : ''));

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

    /** @param array<int, array> $positions */
    private function positionsOf(array $positions): ?string
    {
        if ($positions === []) {
            return null;
        }

        $member = new Member(['id' => 0]);
        $member->useStaffPositions($positions);

        return $member->getStaffTitles()->join(':') ?: null;
    }

    /**
     * Nicknames are "Name - VID" for members and "Name | BR-XX IVAO-YY" for staff.
     */
    private function nameFromNickname(?string $nickname): ?string
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
