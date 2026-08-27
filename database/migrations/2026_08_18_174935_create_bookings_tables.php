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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('event_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->comment('The primary host.')->constrained()->cascadeOnDelete();
            $table->string('status')->default('confirmed');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('invitee_timezone')->default('UTC');
            $table->string('name');
            $table->string('email');
            $table->text('notes')->nullable();
            $table->string('location_type');
            $table->string('location_detail')->nullable();
            $table->string('meeting_url')->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->string('canceled_by')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'starts_at']);
            $table->index(['event_type_id', 'status', 'starts_at']);
            $table->index(['team_id', 'starts_at']);
        });

        Schema::create('booking_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'user_id']);
        });

        Schema::create('booking_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'email']);
        });

        Schema::create('booking_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_type_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->text('answer')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minutes_before');
            $table->timestamp('send_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['send_at', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_reminders');
        Schema::dropIfExists('booking_answers');
        Schema::dropIfExists('booking_guests');
        Schema::dropIfExists('booking_hosts');
        Schema::dropIfExists('bookings');
    }
};
