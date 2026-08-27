<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the registration screen is not reachable without an invitation', function () {
    $response = $this->get(route('register'));

    $response->assertRedirect(route('login'));
});

test('registration screen includes team invitation context', function () {
    $invitation = invitationFor('invited@example.com');

    $response = $this->get(route('register', ['invitation' => $invitation->code]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/Register')
        ->where('teamInvitation.code', $invitation->code)
        ->where('teamInvitation.teamName', 'Laravel Team'),
    );
});

test('an expired invitation does not reopen registration', function () {
    $invitation = invitationFor('invited@example.com');
    $invitation->update(['expires_at' => now()->subDay()]);

    $this->get(route('register', ['invitation' => $invitation->code]))
        ->assertRedirect(route('login'));
});

test('invited users can register', function () {
    invitationFor('test@example.com');

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
    $response->assertRedirect(route('dashboard'));
});

test('uninvited users cannot register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Walk In',
        'email' => 'stranger@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();

    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('an accepted invitation cannot be reused to register', function () {
    $invitation = invitationFor('used@example.com');
    $invitation->update(['accepted_at' => now()]);

    $this->post(route('register.store'), [
        'name' => 'Second Try',
        'email' => 'used@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the invitation check ignores address casing', function () {
    invitationFor('Mixed.Case@Example.com');

    $this->post(route('register.store'), [
        'name' => 'Mixed Case',
        'email' => 'mixed.case@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
});
