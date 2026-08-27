<?php

namespace App\Data\Calendar;

readonly class ExternalCalendar
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isPrimary = false,
        public ?string $timezone = null,
    ) {
        //
    }
}
