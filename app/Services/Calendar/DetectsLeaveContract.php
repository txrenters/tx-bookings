<?php

namespace App\Services\Calendar;

use App\Data\Calendar\ExternalLeave;
use App\Exceptions\MailboxAccessDeniedException;
use App\Models\CalendarAccount;
use App\Support\TimeRange;

/**
 * Implemented by providers that can tell us a user has gone away, from the
 * automatic reply they switched on in their own mail client.
 */
interface DetectsLeaveContract
{
    /**
     * Get the leave the account's mailbox is announcing inside the window.
     *
     * @throws MailboxAccessDeniedException When the account never consented to mailbox access.
     */
    public function leavePeriod(CalendarAccount $account, TimeRange $window): ?ExternalLeave;
}
