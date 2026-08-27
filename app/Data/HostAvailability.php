<?php

namespace App\Data;

use App\Support\TimeRange;
use Illuminate\Support\Collection;

/**
 * A host's open time, plus the working windows it was carved out of.
 *
 * The windows are kept because slot start times are stepped from the working
 * window boundary, not from wherever the free time happens to begin.
 */
readonly class HostAvailability
{
    /**
     * @param  Collection<int, TimeRange>  $free
     * @param  Collection<int, TimeRange>  $windows
     */
    public function __construct(
        public Collection $free,
        public Collection $windows,
    ) {
        //
    }
}
