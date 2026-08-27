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
        Schema::create('calendar_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_id');
            $table->string('email');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'external_id']);
        });

        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('timezone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('checks_conflicts')->default(true);
            $table->boolean('is_write_target')->default(false);
            $table->timestamps();

            $table->unique(['calendar_account_id', 'external_id']);
        });

        Schema::create('busy_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'ends_at']);
        });

        Schema::create('booking_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('external_calendar_id');
            $table->string('meeting_url')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'calendar_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_calendar_events');
        Schema::dropIfExists('busy_blocks');
        Schema::dropIfExists('calendars');
        Schema::dropIfExists('calendar_accounts');
    }
};
