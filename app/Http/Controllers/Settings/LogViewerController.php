<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Logs\LogReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogViewerController extends Controller
{
    public function __construct(protected LogReader $logs)
    {
        //
    }

    /**
     * Show the application log.
     */
    public function index(Request $request): Response
    {
        $this->authorizeViewing();

        $files = $this->logs->files();
        $file = $request->string('file')->toString()
            ?: ($files[0]['name'] ?? '');

        $level = $request->string('level', 'all')->toString();
        $search = $request->string('search')->toString();

        return Inertia::render('settings/Logs', [
            'files' => $files,
            'file' => $file,
            'level' => $level,
            'search' => $search,
            'levels' => $file === '' ? [] : $this->logs->levels($file),
            'entries' => $file === ''
                ? []
                : Inertia::defer(fn () => $this->logs->entries($file, $level, $search)),
        ]);
    }

    /**
     * Empty a log file.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeViewing();

        $file = $request->string('file')->toString();

        if ($this->logs->clear($file)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Log cleared.')]);
        }

        return back();
    }

    /**
     * Restrict the viewer to super admins.
     *
     * Log files routinely contain tokens, emails and request payloads, so this
     * is the most sensitive screen in the app — guard the super admin account
     * accordingly. A 404 rather than a 403 keeps its existence quiet.
     */
    protected function authorizeViewing(): void
    {
        abort_unless(request()->user()?->isSuperAdmin(), 404);
    }
}
