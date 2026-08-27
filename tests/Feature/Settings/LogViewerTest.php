<?php

use App\Models\User;
use App\Services\Logs\LogReader;

beforeEach(function () {
    $this->logPath = storage_path('logs/testing-viewer.log');

    file_put_contents($this->logPath, implode("\n", [
        '[2026-08-24 10:00:00] local.INFO: Booking confirmed for Dana',
        '[2026-08-24 10:05:00] local.ERROR: Calendar sync failed',
        '{"exception":"[object] (RuntimeException(code: 0): Token expired)"}',
        '#0 /app/Services/Calendar/Provider.php(88)',
        '[2026-08-24 10:09:00] local.WARNING: Slot already taken',
        '',
    ]));
});

afterEach(function () {
    @unlink($this->logPath);
});

test('entries are parsed newest first with their stack trace attached', function () {
    $entries = app(LogReader::class)->entries('testing-viewer.log');

    expect($entries)->toHaveCount(3)
        ->and($entries[0]['level'])->toBe('warning')
        ->and($entries[0]['message'])->toBe('Slot already taken')
        ->and($entries[1]['level'])->toBe('error')
        ->and($entries[1]['context'])->toContain('Token expired')
        ->and($entries[1]['context'])->toContain('#0 /app/Services/Calendar/Provider.php');
});

test('entries can be filtered by level and by search', function () {
    $reader = app(LogReader::class);

    expect($reader->entries('testing-viewer.log', 'error'))->toHaveCount(1)
        ->and($reader->entries('testing-viewer.log', 'all', 'Dana'))->toHaveCount(1)
        ->and($reader->entries('testing-viewer.log', 'all', 'nothing here'))->toHaveCount(0);
});

test('the levels present in a file are listed for the filter', function () {
    expect(app(LogReader::class)->levels('testing-viewer.log'))
        ->toBe(['error', 'info', 'warning']);
});

test('a path outside the log directory is refused', function () {
    $reader = app(LogReader::class);

    foreach (['../../.env', '..%2F.env', '/etc/passwd', 'notes.txt', ''] as $attempt) {
        expect($reader->entries($attempt))->toBe([])
            ->and($reader->clear($attempt))->toBeFalse();
    }

    // The env file must still be intact.
    expect(file_get_contents(base_path('.env')))->toContain('APP_NAME');
});

test('a log file can be emptied without being removed', function () {
    expect(app(LogReader::class)->clear('testing-viewer.log'))->toBeTrue()
        ->and(file_exists($this->logPath))->toBeTrue()
        ->and(file_get_contents($this->logPath))->toBe('');
});

test('the viewer page renders for a super admin', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)
        ->get(route('logs.index', ['file' => 'testing-viewer.log']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Logs'));
});

test('an ordinary signed in user cannot see the viewer', function () {
    // 404 rather than 403, so the screen's existence stays quiet.
    $this->actingAs(User::factory()->create())
        ->get(route('logs.index'))
        ->assertNotFound();
});

test('an ordinary user cannot clear a log either', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('logs.destroy'), ['file' => 'testing-viewer.log'])
        ->assertNotFound();

    expect(file_get_contents($this->logPath))->toContain('Calendar sync failed');
});

test('guests cannot reach the viewer', function () {
    $this->get(route('logs.index'))->assertRedirect(route('login'));
});
