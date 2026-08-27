<?php

use App\Models\User;

test('the appearance settings page is available to authenticated users', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk();
});

test('the rendered document defaults to the system appearance', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertDontSee('<html lang="en" class="dark"', false);
});

test('the dark class is rendered when the appearance cookie is dark', function () {
    $response = $this
        ->withUnencryptedCookie('appearance', 'dark')
        ->get(route('login'));

    $response->assertOk();
    $response->assertSee('class="dark"', false);
});

test('the dark class is not rendered when the appearance cookie is light', function () {
    $response = $this
        ->withUnencryptedCookie('appearance', 'light')
        ->get(route('login'));

    $response->assertOk();
    $response->assertDontSee('class="dark"', false);
});

test('the pre-hydration background colors match the theme tokens', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $blade = file_get_contents(resource_path('views/app.blade.php'));

    /**
     * Read the two --background tokens out of app.css rather than hard-coding
     * them, so a palette change only has to be made in one place. The point of
     * the assertion is that the inline pre-hydration style still agrees with
     * the stylesheet, not that the theme is any particular colour.
     */
    $backgroundFor = function (string $selector) use ($css): string {
        expect($css)->toMatch('/'.preg_quote($selector, '/').'\s*\{[^}]*--background:\s*[^;]+;/s');

        preg_match('/'.preg_quote($selector, '/').'\s*\{[^}]*?--background:\s*([^;]+);/s', $css, $matches);

        return trim($matches[1]);
    };

    expect($blade)->toContain('background-color: '.$backgroundFor(':root').';')
        ->and($blade)->toContain('background-color: '.$backgroundFor('.dark').';');
});
