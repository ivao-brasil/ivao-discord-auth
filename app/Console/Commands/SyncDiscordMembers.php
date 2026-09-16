<?php

namespace App\Console\Commands;

use App\Application\Sync\MemberSyncService;
use App\Application\Sync\SyncResult;
use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use Illuminate\Console\Command;

class SyncDiscordMembers extends Command
{
    protected $signature = 'discord:sync
        {vid? : Sync only the accounts linked to this VID}
        {--dry-run : List what would change without touching Discord}';

    protected $description = 'Update Discord roles and nicknames of linked members from their IVAO data';

    public function handle(MemberSyncService $sync, ConsentmentServiceContract $consentments): int
    {
        if ($this->option('dry-run')) {
            return $this->preview($sync, $consentments);
        }

        if ($vid = $this->argument('vid')) {
            $accounts = $consentments->getActiveAccounts($vid);

            if ($accounts->isEmpty()) {
                $this->error("No linked Discord account for VID {$vid}.");

                return self::FAILURE;
            }

            $accounts->each(fn (ConsentmentModel $account) => $this->report($account, $sync->sync($account)));

            return self::SUCCESS;
        }

        $summary = $sync->syncAll(function (ConsentmentModel $account, ?SyncResult $result, ?\Throwable $error) {
            $error ? $this->error("{$account->userVid}: {$error->getMessage()}") : $this->report($account, $result);
        });

        $this->info("Checked {$summary['checked']}, updated {$summary['updated']}, away {$summary['away']}, failed {$summary['failed']}.");

        if ($summary['aborted'] ?? false) {
            $this->error("Stopped after removing roles from {$summary['removed']} members. Check the data before running it again.");

            return self::FAILURE;
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Walks the same accounts as a real run and prints the changes it would make.
     */
    private function preview(MemberSyncService $sync, ConsentmentServiceContract $consentments): int
    {
        $accounts = ($vid = $this->argument('vid'))
            ? $consentments->getActiveAccounts($vid)
            : $consentments->allActive();

        $counts = ['checked' => 0, 'changed' => 0, 'away' => 0, 'unverified' => 0, 'failed' => 0, 'roles' => 0, 'nicknames' => 0];
        $delay = (int) config('brauth.sync.delay_ms') * 1000;

        foreach ($accounts as $account) {
            $counts['checked']++;

            // The same pace as a real run, so a preview never floods Discord
            usleep($delay);

            try {
                $plan = $sync->plan($account);
            } catch (\Throwable $e) {
                $counts['failed']++;
                $this->error("{$account->userVid}: {$e->getMessage()}");

                continue;
            }

            if ($plan->isAway()) {
                $counts['away']++;

                continue;
            }

            if ($plan->unverified) {
                $counts['unverified']++;

                continue;
            }

            if (! $plan->hasChanges()) {
                continue;
            }

            $counts['changed']++;
            $counts['roles'] += count($plan->add) + count($plan->remove);
            $counts['nicknames'] += $plan->nickname ? 1 : 0;

            $this->line(sprintf(
                '%s: +[%s] -[%s]%s',
                $account->userVid,
                implode(', ', $plan->add),
                implode(', ', $plan->remove),
                $plan->nickname ? " nickname \"{$plan->nickname}\"" : ''
            ));
        }

        $this->newLine();
        $this->info("Would change {$counts['changed']} of {$counts['checked']} members: {$counts['roles']} role changes, {$counts['nicknames']} nicknames.");
        $this->info("Away {$counts['away']}, unverified {$counts['unverified']}, failed {$counts['failed']}. Nothing was sent to Discord.");

        return self::SUCCESS;
    }

    private function report(ConsentmentModel $account, SyncResult $result): void
    {
        if ($result->status === SyncResult::AWAY) {
            $this->line("{$account->userVid}: not in the server");
        } elseif ($result->status === SyncResult::UNVERIFIED) {
            $this->line("{$account->userVid}: IVAO did not answer for this account, nothing changed");
        } elseif ($result->hasChanges()) {
            $this->line(sprintf(
                '%s: +[%s] -[%s]%s',
                $account->userVid,
                implode(', ', $result->added),
                implode(', ', $result->removed),
                $result->nickname ? " nickname \"{$result->nickname}\"" : ''
            ));
        } elseif ($this->output->isVerbose()) {
            $this->line("{$account->userVid}: up to date");
        }
    }
}
