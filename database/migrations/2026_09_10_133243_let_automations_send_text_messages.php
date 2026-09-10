<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            /*
             * What the workflow sends: email, a text message, or both. A list
             * rather than a column per channel, so "when someone books, email
             * and text" stays one rule with one set of runs behind it.
             */
            $table->text('channels')->nullable()->after('recipient');
            // Its own wording: a text has no subject and no room for markdown.
            $table->text('sms_body')->nullable()->after('body');
            $table->text('recipient_phones')->nullable()->after('recipient_emails');
        });

        DB::table('automations')->update(['channels' => json_encode(['email'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn(['channels', 'sms_body', 'recipient_phones']);
        });
    }
};
