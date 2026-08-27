<?php

use App\Models\CalendarAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-id',
        'services.google.client_secret' => 'google-secret',
        'services.microsoft.client_id' => 'ms-id',
        'services.microsoft.client_secret' => 'ms-secret',
        'services.microsoft.tenant' => 'common',
    ]);

    $this->user = User::factory()->create();
});

test('calendar settings lists providers and their configuration state', function () {
    $this->actingAs($this->user)
        ->get(route('availability.calendars', ['current_team' => $this->user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('scheduling/availability/Calendars')
            ->has('providers', 2)
            ->where('providers.0.isConfigured', true));
});

test('connecting redirects to the provider with the expected scopes', function () {
    $response = $this->actingAs($this->user)
        ->get(route('integrations.connect', ['provider' => 'google']));

    $response->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
    $response->assertRedirectContains('access_type=offline');
    $response->assertRedirectContains('calendar.events');

    expect(session('calendar_oauth_state'))->not->toBeNull();
});

test('an unconfigured provider cannot be connected', function () {
    config(['services.microsoft.client_id' => null]);

    $this->actingAs($this->user)
        ->get(route('integrations.connect', ['provider' => 'microsoft']))
        ->assertNotFound();
});

test('the callback stores the account and its calendars', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
        ]),
        'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
            'sub' => 'google-user-1',
            'email' => 'dana@example.com',
        ]),
        'www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response([
            'items' => [
                ['id' => 'primary', 'summary' => 'Dana', 'primary' => true, 'timeZone' => 'America/Chicago'],
                ['id' => 'holidays', 'summary' => 'Holidays'],
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->withSession(['calendar_oauth_state' => 'state-token'])
        ->get(route('integrations.callback', ['provider' => 'google']).'?code=auth-code&state=state-token')
        ->assertRedirect(route('availability.calendars'));

    $account = CalendarAccount::first();

    expect($account)->not->toBeNull()
        ->and($account->email)->toBe('dana@example.com')
        ->and($account->access_token)->toBe('access-1')
        ->and($account->refresh_token)->toBe('refresh-1')
        ->and($account->calendars)->toHaveCount(2)
        ->and($account->writeTarget()->external_id)->toBe('primary');
});

test('a callback with a mismatched state is rejected', function () {
    Http::fake();

    $this->actingAs($this->user)
        ->withSession(['calendar_oauth_state' => 'expected'])
        ->get(route('integrations.callback', ['provider' => 'google']).'?code=auth-code&state=forged')
        ->assertRedirect(route('availability.calendars'));

    expect(CalendarAccount::count())->toBe(0);

    Http::assertNothingSent();
});

test('reconnecting the same account updates it instead of duplicating', function () {
    CalendarAccount::factory()->for($this->user)->create([
        'external_id' => 'google-user-1',
        'email' => 'old@example.com',
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-2', 'expires_in' => 3600]),
        'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['sub' => 'google-user-1', 'email' => 'dana@example.com']),
        'www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response(['items' => []]),
    ]);

    $this->actingAs($this->user)
        ->withSession(['calendar_oauth_state' => 'state-token'])
        ->get(route('integrations.callback', ['provider' => 'google']).'?code=code&state=state-token');

    expect(CalendarAccount::count())->toBe(1)
        ->and(CalendarAccount::first()->email)->toBe('dana@example.com');
});

test('tokens are encrypted at rest', function () {
    $account = CalendarAccount::factory()->for($this->user)->create(['access_token' => 'plain-token']);

    $stored = DB::table('calendar_accounts')->where('id', $account->id)->value('access_token');

    expect($stored)->not->toBe('plain-token')
        ->and($account->fresh()->access_token)->toBe('plain-token');
});

test('a user cannot disconnect someone elses calendar', function () {
    $account = CalendarAccount::factory()->for(User::factory())->create();

    $this->actingAs($this->user)
        ->delete(route('integrations.destroy', ['calendarAccount' => $account->id]))
        ->assertForbidden();
});

test('marking a calendar as the write target clears the previous one', function () {
    $account = CalendarAccount::factory()->for($this->user)->create();

    $first = $account->calendars()->create([
        'external_id' => 'a', 'name' => 'A', 'is_write_target' => true,
    ]);
    $second = $account->calendars()->create([
        'external_id' => 'b', 'name' => 'B', 'is_write_target' => false,
    ]);

    $this->actingAs($this->user)
        ->patch(route('integrations.calendars.update', ['calendar' => $second->id]), [
            'checks_conflicts' => true,
            'is_write_target' => true,
        ]);

    expect($first->fresh()->is_write_target)->toBeFalse()
        ->and($second->fresh()->is_write_target)->toBeTrue();
});

test('a provider shows as connected once an account exists', function () {
    $team = $this->user->currentTeam;

    $this->actingAs($this->user)
        ->get(route('availability.calendars', ['current_team' => $team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('providers.0.value', 'microsoft')
            ->where('providers.0.isConnected', false)
            ->where('providers.1.isConnected', false));

    CalendarAccount::factory()->for($this->user)->microsoft()->create();

    $this->actingAs($this->user)
        ->get(route('availability.calendars', ['current_team' => $team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('providers.0.value', 'microsoft')
            ->where('providers.0.isConnected', true)
            ->where('providers.1.isConnected', false));
});
