<?php

use App\Models\User;

/**
 * The log viewer serves raw application logs -- stack traces, request payloads,
 * whatever a log line happens to carry. opcodesio/log-viewer falls back to
 * "local environment only" when nothing defines its gate, so these pin the
 * explicit rule in AppServiceProvider rather than that fallback.
 */
test('a super admin can open the log viewer', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($user)->get('/log-viewer')->assertOk();
});

test('an ordinary user cannot open the log viewer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/log-viewer')->assertForbidden();
});

test('a signed out visitor is sent to log in rather than shown a 403', function () {
    $this->get('/log-viewer')->assertRedirect(route('login'));
});
