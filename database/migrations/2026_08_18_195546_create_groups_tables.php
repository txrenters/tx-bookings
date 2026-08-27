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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        Schema::table('event_types', function (Blueprint $table) {
            // When set, the host pool comes from the group rather than from
            // hosts pinned to this event type one by one.
            $table->foreignId('group_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });

        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
