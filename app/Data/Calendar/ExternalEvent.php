<?php

namespace App\Data\Calendar;

readonly class ExternalEvent
{
    public function __construct(
        public string $id,
        public string $calendarId,
        public ?string $meetingUrl = null,
    ) {
        //
    }
}
