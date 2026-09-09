<?php

namespace App\Console\Commands;

use App\Jobs\SyncCalendarBusyBlocks;
use App\Jobs\SyncLeavePeriods;
use App\Models\CalendarAccount;
use Illuminate\Console\Command;

class SyncCalendars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendars:sync {--days=60 : How far ahead to pull busy times}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh cached busy times and leave from every connected calendar';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $queued = 0;

        // Leave is read from the mailbox, not the calendars, so every account
        // is asked about it even when none of its calendars block time.
        CalendarAccount::query()
            ->with('calendars')
            ->chunkById(100, function ($accounts) use ($days, &$queued) {
                foreach ($accounts as $account) {
                    if ($account->calendars->where('checks_conflicts', true)->isNotEmpty()) {
                        SyncCalendarBusyBlocks::dispatch($account, $days);
                    }

                    SyncLeavePeriods::dispatch($account, $days);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} calendar account(s) for sync.");

        return self::SUCCESS;
    }
}
