<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Optional: only a workflow that texts the hosts needs it, and a
            // host without one is skipped rather than failing the send.
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('teams', function (Blueprint $table) {
            /*
             * Which of the Twilio account's numbers this organization's texts
             * come from. Null means it has not chosen one, and nothing of
             * theirs can be texted until an admin does.
             */
            $table->string('sms_from_number')->nullable()->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('sms_from_number');
        });
    }
};
