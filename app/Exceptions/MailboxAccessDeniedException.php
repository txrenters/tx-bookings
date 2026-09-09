<?php

namespace App\Exceptions;

use RuntimeException;

class MailboxAccessDeniedException extends RuntimeException
{
    /**
     * The account predates the mailbox scope and has to consent again.
     */
    public static function needsReconnect(string $email): self
    {
        return new self("Reconnect {$email} to let us see out of office replies.");
    }
}
