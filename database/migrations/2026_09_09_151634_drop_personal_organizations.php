<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire the personal organization.
     *
     * Registration is invitation-only, so every account now lands in the
     * organization that invited it. The ones already created stay as ordinary
     * organizations -- deleting them would take their owner's availability,
     * event types and bookings with them.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_personal');
        });
    }

    /**
     * Restores the column, empty: which organizations were personal is not
     * recoverable, and nothing creates them any more.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false);
        });
    }
};
