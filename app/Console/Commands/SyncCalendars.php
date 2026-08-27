<?php

namespace App\Console\Commands;

use App\Jobs\SyncCalendarBusyBlocks;
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
    protected $description = 'Refresh cached busy times from every connected calendar';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $queued = 0;

        CalendarAccount::query()
            ->whereHas('calendars', fn ($calendars) => $calendars->where('checks_conflicts', true))
            ->chunkById(100, function ($accounts) use ($days, &$queued) {
                foreach ($accounts as $account) {
                    SyncCalendarBusyBlocks::dispatch($account, $days);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} calendar account(s) for sync.");

        return self::SUCCESS;
    }
}
