<?php

use App\Actions\Scheduling\ApplyDefaultHolidays;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $country = config('scheduling.default_holiday_country');

        if (blank($country)) {
            return;
        }

        $apply = app(ApplyDefaultHolidays::class);

        // Only people who have never chosen a country, so anyone who has
        // deliberately opted out is left alone.
        User::query()
            ->whereNull('holiday_country')
            ->chunkById(100, function ($users) use ($apply, $country) {
                foreach ($users as $user) {
                    $apply->handle($user, $country);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Opting people back out would discard choices they have since made.
    }
};
