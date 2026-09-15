<?php

namespace App\Console\Commands;

use App\Application\Sync\MemberSyncService;
use App\Application\Sync\SyncResult;
use App\ConsentmentModel;
use App\Domain\Contracts\ConsentmentServiceContract;
use Illuminate\Console\Command;

class SyncDiscordMembers extends Command
{
    protected $signature = 'discord:sync {vid? : Sync only the accounts linked to this VID}';

    protected $description = 'Update Discord roles and nicknames of linked members from their IVAO data';

    public function handle(MemberSyncService $sync, ConsentmentServiceContract $consentments): int
    {
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

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function report(ConsentmentModel $account, SyncResult $result): void
    {
        if ($result->status === SyncResult::AWAY) {
            $this->line("{$account->userVid}: not in the server");
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
