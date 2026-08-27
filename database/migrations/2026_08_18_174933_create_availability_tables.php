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
        Schema::create('availability_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('availability_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['availability_schedule_id', 'day_of_week']);
        });

        Schema::create('availability_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('availability_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('is_unavailable')->default(false);
            $table->timestamps();

            $table->index(['availability_schedule_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_overrides');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('availability_schedules');
    }
};
