<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a schedule belong to an organization instead of a person.
     *
     * A shared schedule is the hours an event type keeps whoever hosts it --
     * "Leasing takes bookings 9-3" -- rather than every member maintaining
     * matching hours of their own and a new member quietly booking outside
     * the window.
     *
     * Exactly one of user_id and team_id is set. The application enforces it:
     * a CHECK constraint is not portable across the engines this runs on.
     */
    public function up(): void
    {
        Schema::table('availability_schedules', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('availability_schedules', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('availability_schedules', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            // nullOnDelete, not cascade: deleting the hours must not delete the
            // team that keeps them.
            $table->foreignId('availability_schedule_id')
                ->nullable()
                ->after('description')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Shared schedules are deleted rather than reassigned: they belong to no
     * one once the column is gone, and user_id goes back to being required.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('availability_schedule_id');
        });

        // Shared schedules have no owner to fall back to once team_id is gone.
        DB::table('availability_schedules')->whereNull('user_id')->delete();

        Schema::table('availability_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('availability_schedules', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('availability_schedules', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('availability_schedules', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
