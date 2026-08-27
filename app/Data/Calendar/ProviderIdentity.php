<?php

namespace App\Data\Calendar;

use Carbon\CarbonImmutable;

readonly class ProviderIdentity
{
    public function __construct(
        public string $externalId,
        public string $email,
        public string $accessToken,
        public ?string $refreshToken,
        public ?CarbonImmutable $expiresAt,
    ) {
        //
    }
}
