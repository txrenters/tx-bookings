<?php

use App\Models\Booking;
use App\Models\EventType;
use App\Models\User;
use App\Notifications\Bookings\BookingCanceled;
use App\Notifications\Bookings\BookingConfirmed;
use App\Notifications\Bookings\BookingDeclined;
use App\Notifications\Bookings\BookingPendingApproval;
use App\Notifications\Bookings\BookingRescheduled;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Build a booking with a real host and event type.
 */
function notifiableBooking(): Booking
{
    $host = User::factory()->create(['timezone' => 'America/Chicago']);
    $eventType = EventType::factory()->ownedBy($host)->create(['name' => 'Property Tour']);

    return Booking::factory()->create([
        'event_type_id' => $eventType->id,
        'team_id' => $eventType->team_id,
        'user_id' => $host->id,
        'name' => 'Dana Reyes',
        'email' => 'dana@example.com',
    ]);
}

test('a host is notified in the app as well as by mail', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    expect((new BookingConfirmed($booking))->via($host))
        ->toBe(['mail', 'database']);
});

test('an invitee only gets mail, never an in-app notification', function () {
    $booking = notifiableBooking();

    // Invitees are reached as anonymous notifiables; they have no account.
    expect((new BookingConfirmed($booking))->via(new AnonymousNotifiable))
        ->toBe(['mail']);
});

test('every booking notification stores a readable payload', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    $cases = [
        [new BookingConfirmed($booking), 'booking.confirmed', 'New booking', 'booked'],
        [new BookingCanceled($booking), 'booking.canceled', 'Booking canceled', 'canceled'],
        [new BookingRescheduled($booking, $booking), 'booking.rescheduled', 'Booking moved', 'moved'],
        [new BookingPendingApproval($booking), 'booking.pending', 'Approval needed', 'requested'],
        [new BookingDeclined($booking), 'booking.declined', 'Request declined', 'declined'],
    ];

    foreach ($cases as [$notification, $type, $title, $verb]) {
        $payload = $notification->toDatabase($host);

        expect($payload['type'])->toBe($type)
            ->and($payload['title'])->toBe($title)
            ->and($payload['body'])->toContain('Dana Reyes')
            ->and($payload['body'])->toContain($verb)
            ->and($payload['body'])->toContain('Property Tour')
            ->and($payload['bookingUid'])->toBe($booking->uid)
            ->and($payload['whenLabel'])->not->toBeEmpty();
    }
});

test('a real booking lands in the host notifications table', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    $host->notify(new BookingConfirmed($booking));

    $this->assertDatabaseCount('notifications', 1);

    expect($host->unreadNotifications()->count())->toBe(1)
        ->and($host->notifications()->first()->data['type'])->toBe('booking.confirmed');
});

test('the panel lists notifications with an unread count', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    $host->notify(new BookingConfirmed($booking));

    $response = $this->actingAs($host)->getJson(route('notifications.index'));

    $response->assertOk()
        ->assertJsonPath('unreadCount', 1)
        ->assertJsonPath('notifications.0.title', 'New booking')
        ->assertJsonPath('notifications.0.readAt', null);
});

test('one notification can be marked read', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    $host->notify(new BookingConfirmed($booking));
    $id = $host->notifications()->first()->id;

    $this->actingAs($host)->patch(route('notifications.update', $id));

    expect($host->fresh()->unreadNotifications()->count())->toBe(0);
});

test('every notification can be marked read at once', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    $host->notify(new BookingConfirmed($booking));
    $host->notify(new BookingCanceled($booking));

    expect($host->unreadNotifications()->count())->toBe(2);

    $this->actingAs($host)->post(route('notifications.read-all'));

    expect($host->fresh()->unreadNotifications()->count())->toBe(0);
});

test('a notification can be dismissed', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    $host->notify(new BookingConfirmed($booking));
    $id = $host->notifications()->first()->id;

    $this->actingAs($host)->delete(route('notifications.destroy', $id));

    $this->assertDatabaseCount('notifications', 0);
});

test('a user cannot touch another users notifications', function () {
    $booking = notifiableBooking();
    $host = $booking->host;
    $stranger = User::factory()->create();

    $host->notify(new BookingConfirmed($booking));
    $id = $host->notifications()->first()->id;

    $this->actingAs($stranger)->delete(route('notifications.destroy', $id));
    $this->actingAs($stranger)->patch(route('notifications.update', $id));

    $this->assertDatabaseCount('notifications', 1);
    expect($host->fresh()->unreadNotifications()->count())->toBe(1);
});

test('the unread count is shared with every page', function () {
    $booking = notifiableBooking();
    $host = $booking->host;

    $host->notify(new BookingConfirmed($booking));

    $this->actingAs($host)
        ->get(route('dashboard', ['current_team' => $host->currentTeam->slug]))
        ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
});
