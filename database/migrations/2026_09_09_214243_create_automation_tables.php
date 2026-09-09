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
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger');
            // Null for "when booked"; minutes for the timed triggers.
            $table->unsignedInteger('offset_minutes')->nullable();
            $table->string('recipient');
            $table->string('recipient_email')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        // No rows means every event type in the organization, which is how
        // "Applies to: all" is expressed without a magic value.
        Schema::create('automation_event_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained()->cascadeOnDelete();

            $table->unique(['automation_id', 'event_type_id']);
        });

        /*
         * One row per booking per automation, the same shape booking_reminders
         * has: due work the scheduler can pick up, and a record of what was
         * sent so a re-run cannot send it twice.
         */
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->timestamp('send_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('failure')->nullable();
            $table->timestamps();

            $table->index(['send_at', 'sent_at']);
            $table->unique(['automation_id', 'booking_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_event_type');
        Schema::dropIfExists('automations');
    }
};
