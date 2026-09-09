<?php

use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fold the owner role into admin.
     *
     * Organizations are run by their administrators now; nothing sits above
     * them inside an organization, and anything above one is a super admin.
     * Rows carrying the retired role would otherwise fail TeamRole::from()
     * the moment they were read.
     */
    public function up(): void
    {
        DB::table('team_members')
            ->where('role', 'owner')
            ->update(['role' => TeamRole::Admin->value]);

        DB::table('team_invitations')
            ->where('role', 'owner')
            ->update(['role' => TeamRole::Admin->value]);
    }

    /**
     * The distinction is gone; there is no owner to restore anyone to.
     */
    public function down(): void
    {
        //
    }
};
