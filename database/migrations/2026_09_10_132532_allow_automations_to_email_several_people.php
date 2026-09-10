<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Someone else" is often a desk rather than a person -- leasing and
     * accounting both want the same booking -- so the single address becomes
     * a list.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->text('recipient_emails')->nullable()->after('recipient');
        });

        foreach (DB::table('automations')->whereNotNull('recipient_email')->get(['id', 'recipient_email']) as $automation) {
            DB::table('automations')
                ->where('id', $automation->id)
                ->update(['recipient_emails' => json_encode([$automation->recipient_email])]);
        }

        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('recipient_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->string('recipient_email')->nullable()->after('recipient');
        });

        foreach (DB::table('automations')->whereNotNull('recipient_emails')->get(['id', 'recipient_emails']) as $automation) {
            $emails = json_decode((string) $automation->recipient_emails, true);

            DB::table('automations')
                ->where('id', $automation->id)
                ->update(['recipient_email' => is_array($emails) ? ($emails[0] ?? null) : null]);
        }

        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('recipient_emails');
        });
    }
};
