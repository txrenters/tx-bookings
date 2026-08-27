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
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->comment('The owner of the event type.')->constrained()->cascadeOnDelete();
            $table->foreignId('availability_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('one_on_one');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#0f766e');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('slot_interval_minutes')->nullable();
            $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
            $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
            $table->unsignedInteger('minimum_notice_minutes')->default(240);
            $table->unsignedSmallInteger('daily_booking_limit')->nullable();
            $table->unsignedSmallInteger('seats_per_slot')->default(1);
            $table->string('date_range_type')->default('rolling_days');
            $table->unsignedSmallInteger('rolling_days')->default(60);
            $table->date('range_starts_on')->nullable();
            $table->date('range_ends_on')->nullable();
            $table->string('location_type')->default('microsoft_teams');
            $table->string('location_detail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('requires_confirmation')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('event_type_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('availability_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['event_type_id', 'user_id']);
        });

        Schema::create('event_type_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_type_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('text');
            $table->string('label');
            $table->string('help_text')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_type_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_type_questions');
        Schema::dropIfExists('event_type_hosts');
        Schema::dropIfExists('event_types');
    }
};
