<?php

namespace App\Enums;

enum EventTypeKind: string
{
    case OneOnOne = 'one_on_one';
    case Group = 'group';
    case RoundRobin = 'round_robin';
    case Collective = 'collective';

    /**
     * Get the display label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::OneOnOne => 'One-on-one',
            self::Group => 'Group',
            self::RoundRobin => 'Round robin',
            self::Collective => 'Collective',
        };
    }

    /**
     * Get the shape of the meeting, as hosts to invitees.
     */
    public function flow(): string
    {
        return match ($this) {
            self::OneOnOne => '1 host → 1 invitee',
            self::Group => '1 host → Multiple invitees',
            self::RoundRobin => 'Rotating hosts → 1 invitee',
            self::Collective => 'Multiple hosts → 1 invitee',
        };
    }

    /**
     * Get the description shown when picking an event type kind.
     */
    public function description(): string
    {
        return match ($this) {
            self::OneOnOne => 'Good for coffee chats, 1:1 interviews, etc.',
            self::Group => 'Webinars, online classes, etc.',
            self::RoundRobin => 'Distribute meetings between organization members',
            self::Collective => 'Panel interviews, group sales calls, etc.',
        };
    }

    /**
     * Determine if the kind draws from a pool of several hosts.
     */
    public function hasHostPool(): bool
    {
        return in_array($this, [self::RoundRobin, self::Collective], true);
    }

    /**
     * Determine if every host in the pool must be free for a slot to be offered.
     */
    public function requiresEveryHost(): bool
    {
        return $this === self::Collective;
    }

    /**
     * Determine if the kind allows more than one invitee per slot.
     */
    public function allowsMultipleInvitees(): bool
    {
        return $this === self::Group;
    }

    /**
     * Determine if the kind may only be used by a shared (non personal) team.
     */
    public function requiresTeam(): bool
    {
        return $this->hasHostPool();
    }

    /**
     * Get the kinds as select options.
     *
     * @return array<array{value: string, label: string, flow: string, description: string, requiresTeam: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $kind) => [
            'value' => $kind->value,
            'label' => $kind->label(),
            'flow' => $kind->flow(),
            'description' => $kind->description(),
            'requiresTeam' => $kind->requiresTeam(),
        ], self::cases());
    }
}
