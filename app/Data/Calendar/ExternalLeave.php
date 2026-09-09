<?php

namespace App\Data\Calendar;

use Carbon\CarbonImmutable;

readonly class ExternalLeave
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?string $message = null,
    ) {
        //
    }
}
