<?php

use App\Enums\BookingStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ActivityLog;
use App\Models\AvailabilitySchedule;
use App\Models\Booking;
use App\Models\EventType;
use App\Models\User;
use App\Notifications\Bookings\BookingCanceled;
use App\Notifications\Bookings\BookingConfirmed;
use App\Notifications\Bookings\BookingRescheduled;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 08:00:00', 'UTC'));

    $this->host = User::factory()->create([
        'name' => 'Dana Reed',
        'booking_slug' => 'dana',
        'timezone' => 'UTC',
    ]);

    AvailabilitySchedule::factory()->for($this->host)->everyDay()->create();

    $this->eventType = EventType::factory()->ownedBy($this->host)->create([
        'name' => 'Intro call',
        'slug' => 'intro',
        'duration_minutes' => 60,
    ]);
});

test('the public page lists an owner bookable event types', function () {
    $this->get(route('book.page', ['page' => 'dana']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('book/Page')
            ->where('page.name', 'Dana Reed')
            ->has('eventTypes', 1)
            ->where('eventTypes.0.slug', 'intro'));
});

test('hidden event types are left off the public page but still resolve directly', function () {
    $this->eventType->update(['is_hidden' => true]);

    $this->get(route('book.page', ['page' => 'dana']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('eventTypes', 0));

    $this->get(route('book.event-type', ['page' => 'dana', 'eventType' => 'intro']))
        ->assertOk();
});

test('an inactive event type is not bookable', function () {
    $this->eventType->update(['is_active' => false]);

    $this->get(route('book.event-type', ['page' => 'dana', 'eventType' => 'intro']))
        ->assertNotFound();
});

test('an unknown page returns a not found response', function () {
    $this->get(route('book.page', ['page' => 'nobody']))->assertNotFound();
});

test('the event type page renders without waiting on the slot lookup', function () {
    $this->get(route('book.event-type', ['page' => 'dana', 'eventType' => 'intro', 'month' => '2026-09']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('book/EventType')
            ->where('eventType.name', 'Intro call')
            ->where('month', '2026-09')
            ->where('timezone', 'UTC')
            ->missing('slots'));
});

test('the deferred slot lookup returns the open times grouped by local date', function () {
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Data' => 'slots',
        'X-Inertia-Partial-Component' => 'book/EventType',
        'X-Inertia-Version' => (new HandleInertiaRequests)->version(request()),
    ])->get(route('book.event-type', ['page' => 'dana', 'eventType' => 'intro', 'month' => '2026-09']));

    $slots = $response->assertOk()->json('props.slots');

    expect($slots)->toHaveKey('2026-09-02')
        ->and($slots['2026-09-02'][0]['label'])->toBe('9:00am')
        ->and($slots['2026-09-02'])->toHaveCount(8);
});

test('the deferred slot lookup honours the requested timezone', function () {
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Data' => 'slots',
        'X-Inertia-Partial-Component' => 'book/EventType',
        'X-Inertia-Version' => (new HandleInertiaRequests)->version(request()),
    ])->get(route('book.event-type', [
        'page' => 'dana',
        'eventType' => 'intro',
        'month' => '2026-09',
        'timezone' => 'America/Chicago',
    ]));

    $slots = $response->assertOk()->json('props.slots');

    // 09:00 UTC is 04:00 in Chicago on that date.
    expect($slots['2026-09-02'][0]['label'])->toBe('4:00am');
});

test('an invitee can book a slot', function () {
    Notification::fake();

    $startsAt = CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC');

    $response = $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), [
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'America/Chicago',
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'notes' => 'Looking forward to it.',
    ]);

    $booking = Booking::first();

    expect($booking)->not->toBeNull()
        ->and($booking->starts_at->toIso8601String())->toBe($startsAt->toIso8601String())
        ->and($booking->ends_at->toIso8601String())->toBe($startsAt->addHour()->toIso8601String())
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->user_id)->toBe($this->host->id)
        ->and($booking->invitee_timezone)->toBe('America/Chicago');

    $response->assertRedirect(route('booking.show', ['booking' => $booking->uid]));

    Notification::assertSentTo($this->host, BookingConfirmed::class);
});

test('booking a slot schedules reminders', function () {
    Notification::fake();

    $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), [
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC')->toIso8601String(),
        'timezone' => 'UTC',
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
    ]);

    expect(Booking::first()->reminders()->count())->toBe(2);
});

test('the same slot cannot be booked twice', function () {
    Notification::fake();

    $payload = [
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC')->toIso8601String(),
        'timezone' => 'UTC',
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
    ];

    $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), $payload);

    $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), $payload)
        ->assertSessionHasErrors('starts_at');

    expect(Booking::count())->toBe(1);
});

test('a slot outside the working hours is rejected', function () {
    Notification::fake();

    $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), [
        'starts_at' => CarbonImmutable::parse('2026-09-02 03:00:00', 'UTC')->toIso8601String(),
        'timezone' => 'UTC',
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
    ])->assertSessionHasErrors('starts_at');

    expect(Booking::count())->toBe(0);
});

test('a booking captures guests and answers to custom questions', function () {
    Notification::fake();

    $question = $this->eventType->questions()->create([
        'type' => 'text',
        'label' => 'What would you like to cover?',
        'is_required' => true,
        'position' => 0,
    ]);

    $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), [
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC')->toIso8601String(),
        'timezone' => 'UTC',
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'guests' => ['colleague@example.com'],
        'answers' => [$question->id => 'Pricing'],
    ]);

    $booking = Booking::first();

    expect($booking->guests->pluck('email')->all())->toBe(['colleague@example.com'])
        ->and($booking->answers->first()->label)->toBe('What would you like to cover?')
        ->and($booking->answers->first()->answer)->toBe('Pricing');
});

test('a required question must be answered', function () {
    Notification::fake();

    $question = $this->eventType->questions()->create([
        'type' => 'text',
        'label' => 'What would you like to cover?',
        'is_required' => true,
        'position' => 0,
    ]);

    $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), [
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC')->toIso8601String(),
        'timezone' => 'UTC',
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
    ])->assertSessionHasErrors("answers.{$question->id}");
});

test('an invitee can cancel their booking', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'event_type_id' => $this->eventType->id,
        'user_id' => $this->host->id,
        'team_id' => $this->eventType->team_id,
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-02 11:00:00', 'UTC'),
    ]);

    $this->delete(route('booking.cancel.store', ['booking' => $booking->uid]), [
        'reason' => 'Something came up',
    ])->assertRedirect(route('booking.show', ['booking' => $booking->uid]));

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Canceled)
        ->and($booking->cancellation_reason)->toBe('Something came up')
        ->and($booking->canceled_by)->toBe('invitee');

    Notification::assertSentTo($this->host, BookingCanceled::class);
});

test('an invitee can reschedule to a free slot', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'event_type_id' => $this->eventType->id,
        'user_id' => $this->host->id,
        'team_id' => $this->eventType->team_id,
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-02 11:00:00', 'UTC'),
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
    ]);

    $this->post(route('booking.reschedule.store', ['booking' => $booking->uid]), [
        'starts_at' => CarbonImmutable::parse('2026-09-03 14:00:00', 'UTC')->toIso8601String(),
        'reason' => 'Conflict',
    ]);

    $booking->refresh();
    $replacement = Booking::where('rescheduled_from_id', $booking->id)->first();

    expect($booking->status)->toBe(BookingStatus::Rescheduled)
        ->and($replacement)->not->toBeNull()
        ->and($replacement->starts_at->toTimeString())->toBe('14:00:00')
        ->and($replacement->email)->toBe('sam@example.com');

    Notification::assertSentTo($this->host, BookingRescheduled::class);
});

test('rescheduling announces the move once, without a second confirmation', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'event_type_id' => $this->eventType->id,
        'user_id' => $this->host->id,
        'team_id' => $this->eventType->team_id,
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-02 11:00:00', 'UTC'),
        'email' => 'sam@example.com',
    ]);

    $this->post(route('booking.reschedule.store', ['booking' => $booking->uid]), [
        'starts_at' => CarbonImmutable::parse('2026-09-03 14:00:00', 'UTC')->toIso8601String(),
    ]);

    Notification::assertSentTo($this->host, BookingRescheduled::class);

    // The replacement is built by CreateBooking, which must stay quiet here.
    Notification::assertNotSentTo($this->host, BookingConfirmed::class);
    Notification::assertNotSentTo(new AnonymousNotifiable, BookingConfirmed::class);

    expect(ActivityLog::pluck('event')->all())->toBe(['booking.rescheduled']);
});

test('rescheduling releases the original slot', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'event_type_id' => $this->eventType->id,
        'user_id' => $this->host->id,
        'team_id' => $this->eventType->team_id,
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-02 11:00:00', 'UTC'),
    ]);

    // Moving one hour later would overlap the original booking unless it is released.
    $this->post(route('booking.reschedule.store', ['booking' => $booking->uid]), [
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC')->toIso8601String(),
    ]);

    expect(Booking::where('rescheduled_from_id', $booking->id)->exists())->toBeTrue();
});

test('a canceled booking cannot be canceled again', function () {
    Notification::fake();

    $booking = Booking::factory()->canceled()->create([
        'event_type_id' => $this->eventType->id,
        'user_id' => $this->host->id,
        'team_id' => $this->eventType->team_id,
    ]);

    $this->delete(route('booking.cancel.store', ['booking' => $booking->uid]))->assertGone();
});

test('the confirmation page shows the booking in the invitee timezone', function () {
    $booking = Booking::factory()->create([
        'event_type_id' => $this->eventType->id,
        'user_id' => $this->host->id,
        'team_id' => $this->eventType->team_id,
        'starts_at' => CarbonImmutable::parse('2026-09-02 15:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-02 16:00:00', 'UTC'),
        'invitee_timezone' => 'America/Chicago',
    ]);

    $this->get(route('booking.show', ['booking' => $booking->uid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('book/Confirmed')
            ->where('booking.localTime', '10:00am - 11:00am')
            ->where('booking.timezone', 'America/Chicago'));
});

test('the booking page falls back to the configured default timezone', function () {
    $host = User::factory()->create(['name' => 'Casey Lane', 'booking_slug' => 'casey']);

    AvailabilitySchedule::factory()->for($host)->everyDay()->create();

    EventType::factory()->ownedBy($host)->create(['slug' => 'chat']);

    expect($host->timezone)->toBe('America/Chicago');

    $this->get(route('book.event-type', ['page' => 'casey', 'eventType' => 'chat']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('timezone', 'America/Chicago'));
});

test('an invitee who picks no timezone is recorded in the configured default', function () {
    Notification::fake();

    $this->post(route('book.store', ['page' => 'dana', 'eventType' => 'intro']), [
        'starts_at' => CarbonImmutable::parse('2026-09-02 10:00:00', 'UTC')->toIso8601String(),
        'timezone' => 'America/Chicago',
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
    ]);

    expect(Booking::first()->invitee_timezone)->toBe('America/Chicago');
});

/*
 * An invitee is offered no self-service reschedule or cancel: changes go
 * through the organizer. The routes themselves stay reachable -- the host's
 * Meetings page uses them, and links already emailed must not start 404ing.
 */
test('the invitee confirmation email offers no reschedule or cancel link', function () {
    $booking = Booking::factory()->create();

    $mail = (new BookingConfirmed($booking))->toMail(new AnonymousNotifiable);

    $body = $mail->actionText.' '.implode(' ', array_merge($mail->introLines, $mail->outroLines));

    expect($body)->not->toContain('Reschedule')
        ->and($body)->not->toContain('Cancel this meeting');
});
