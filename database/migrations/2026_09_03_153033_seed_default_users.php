<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the default users as part of the deploy.
     *
     * No deploy pipeline runs db:seed, and registration is invitation-only,
     * so a production database would otherwise have no way to get its first
     * account — while running migrations is the one thing every deploy does.
     * The seeder skips accounts that already exist, and the test suite is
     * skipped so tests keep an empty baseline.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        (new DatabaseSeeder)->run();
    }

    /**
     * Seeded accounts are left in place; removing users would take their
     * organizations and bookings with them.
     */
    public function down(): void
    {
        //
    }
};
