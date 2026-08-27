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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // Null when nobody was signed in: a public booking, or the console.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
