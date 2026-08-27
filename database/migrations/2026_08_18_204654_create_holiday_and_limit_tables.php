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
            $table->string('holiday_country', 2)->nullable()->after('timezone');
        });

        Schema::create('user_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('holiday_key');
            $table->timestamps();

            $table->unique(['user_id', 'holiday_key']);
        });

        Schema::create('meeting_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period');
            $table->unsignedSmallInteger('max_bookings');
            $table->timestamps();

            $table->unique(['user_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_limits');
        Schema::dropIfExists('user_holidays');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('holiday_country');
        });
    }
};
