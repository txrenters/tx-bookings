<?php

namespace App\Enums;

enum CalendarProvider: string
{
    case Microsoft = 'microsoft';
    case Google = 'google';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Calendar',
            self::Microsoft => 'Microsoft 365',
        };
    }

    /**
     * Get the location type this provider generates for online meetings.
     */
    public function meetingLocationType(): LocationType
    {
        return match ($this) {
            self::Google => LocationType::GoogleMeet,
            self::Microsoft => LocationType::MicrosoftTeams,
        };
    }

    /**
     * Get the config key holding this provider's credentials.
     */
    public function configKey(): string
    {
        return "services.{$this->value}";
    }

    /**
     * Determine if the provider has credentials configured.
     */
    public function isConfigured(): bool
    {
        return filled(config("{$this->configKey()}.client_id"))
            && filled(config("{$this->configKey()}.client_secret"));
    }
}
