<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables and columns that carry a display timezone.
     *
     * @var array<string, string>
     */
    protected array $columns = [
        'users' => 'timezone',
        'teams' => 'timezone',
        'availability_schedules' => 'timezone',
        'bookings' => 'invitee_timezone',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timezone = config('scheduling.default_timezone');

        foreach ($this->columns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $timezone) {
                $blueprint->string($column)->default($timezone)->change();
            });

            // Rows still sitting on the old default have never been chosen
            // deliberately, so move them across too.
            DB::table($table)->where($column, 'UTC')->update([$column => $timezone]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->columns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->string($column)->default('UTC')->change();
            });
        }
    }
};
