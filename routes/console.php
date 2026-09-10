<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('bookings:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Send due booking reminders');

Schedule::command('automations:run')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Send due workflow emails');

Schedule::command('calendars:sync')
    ->hourly()
    ->withoutOverlapping()
    ->description('Refresh cached busy times from connected calendars');
