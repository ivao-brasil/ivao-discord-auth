<?php

namespace App\Console\Commands;

use App\ConsentmentModel;
use Illuminate\Console\Command;

class DedupeMemberLinks extends Command
{
    protected $signature = 'discord:dedupe-links {--dry-run : List the links without deactivating them}';

    protected $description = 'Deactivate repeated links of the same VID to the same Discord account';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deactivated = 0;
        $review = [];

        $groups = ConsentmentModel::where('status', true)
            ->get(['id', 'userVid', 'discordId'])
            ->groupBy('userVid')
            ->filter(fn ($links) => $links->count() > 1);

        foreach ($groups as $userVid => $links) {
            // A VID linked to more than one Discord account is left for a human to decide
            if ($links->pluck('discordId')->unique()->count() > 1) {
                $review[] = $userVid;

                continue;
            }

            $extra = $links->sortByDesc('id')->skip(1);
            $this->line("{$userVid}: keeping link #{$links->max('id')}, dropping ".$extra->count());

            $dryRun || ConsentmentModel::whereIn('id', $extra->pluck('id'))->update(['status' => false]);
            $deactivated += $extra->count();
        }

        foreach ($review as $userVid) {
            $this->warn("{$userVid}: linked to more than one Discord account, left untouched");
        }

        $forReview = count($review);

        $this->info($dryRun
            ? "Would deactivate {$deactivated} repeated links, {$forReview} for review."
            : "Deactivated {$deactivated} repeated links, {$forReview} for review.");

        return self::SUCCESS;
    }
}
