<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One mapping table keeps every Calendly URI we have already seen, so a
     * re-run updates instead of duplicating and no domain table has to carry a
     * vendor-specific column.
     */
    public function up(): void
    {
        Schema::create('calendly_imports', function (Blueprint $table) {
            $table->id();
            $table->string('calendly_uri')->unique();
            $table->string('resource_type')->index();
            $table->nullableMorphs('importable');
            $table->json('payload')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendly_imports');
    }
};
