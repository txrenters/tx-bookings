<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The addresses being freed up so the invitation flow can be tested
     * end to end with them again.
     *
     * @var array<int, string>
     */
    protected array $emails = [
        'digitalmarketing@texasrenters.com',
        'appdev@texasrenters.com',
    ];

    /**
     * Remove the Calendly-imported shell accounts used for invite testing.
     *
     * Deliberately runs on deploy: these accounts exist only on production,
     * where the import created them, and nobody holds their passwords. The
     * user rows go through the foreign keys, so their memberships, imported
     * bookings, and availability go with them; pending invitations for the
     * addresses are cleared too, so the next test starts from a clean slate.
     */
    public function up(): void
    {
        $emails = array_map('strtolower', $this->emails);

        DB::table('team_invitations')
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->delete();

        DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->delete();
    }

    /**
     * The rows are gone for good; recreate accounts through an invitation.
     */
    public function down(): void
    {
        //
    }
};
