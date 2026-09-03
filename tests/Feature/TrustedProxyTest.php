<?php

/**
 * In production the app runs in a container that speaks plain HTTP behind the
 * VPS's nginx, which terminates TLS and forwards the original scheme in
 * X-Forwarded-Proto. Laravel ignores that header unless the proxy is trusted,
 * and then generates http:// asset URLs on an https:// page -- the browser
 * blocks every stylesheet and script as mixed content while the response still
 * returns 200 and nothing is logged.
 *
 * A test request is built from APP_URL, which is https, so the discriminating
 * case is the reverse one: a forwarded *plain* request must be reported as
 * insecure. Untrusted, the header is discarded and the request stays https.
 */
it('honours the protocol the reverse proxy forwards', function () {
    $this->get('/login', ['X-Forwarded-Proto' => 'http'])->assertOk();

    expect(request()->isSecure())->toBeFalse()
        ->and(request()->getScheme())->toBe('http');
});

it('reports a forwarded secure request as secure', function () {
    $this->get('/login', ['X-Forwarded-Proto' => 'https'])->assertOk();

    expect(request()->isSecure())->toBeTrue();
});
