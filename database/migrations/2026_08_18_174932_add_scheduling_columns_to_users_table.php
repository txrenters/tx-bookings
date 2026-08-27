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
            $table->string('booking_slug')->nullable()->unique()->after('email');
            $table->string('timezone')->default('UTC')->after('booking_slug');
            $table->text('welcome_message')->nullable()->after('timezone');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('timezone')->default('UTC')->after('slug');
            $table->text('welcome_message')->nullable()->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['booking_slug', 'timezone', 'welcome_message']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'welcome_message']);
        });
    }
};
