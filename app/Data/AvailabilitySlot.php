<?php

namespace App\Data;

use Carbon\CarbonImmutable;

readonly class AvailabilitySlot
{
    /**
     * @param  array<int, int>  $hostIds
     */
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public array $hostIds,
        public int $seatsRemaining = 1,
    ) {
        //
    }

    /**
     * Get the slot as a payload for the booking page.
     *
     * @return array{startsAt: string, endsAt: string, hostIds: array<int, int>, seatsRemaining: int}
     */
    public function toArray(): array
    {
        return [
            'startsAt' => $this->startsAt->toIso8601String(),
            'endsAt' => $this->endsAt->toIso8601String(),
            'hostIds' => $this->hostIds,
            'seatsRemaining' => $this->seatsRemaining,
        ];
    }
}
