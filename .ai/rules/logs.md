---
paths:
  - config/log-viewer.php
  - app/Providers/AppServiceProvider.php
---

# Logs

## The log viewer is opcodesio/log-viewer, not an in-app page
Logs are read at /log-viewer, served by the package. A hand-rolled Inertia viewer (LogViewerController + App\Services\Logs\LogReader + a settings page) existed alongside it and was removed in 2026-09 in favour of the package — do not rebuild it. The sidebar's "Logs" entry links to the package route through Wayfinder's `@/routes/log-viewer`.

Access is pinned by AppServiceProvider::restrictLogViewerToSuperAdmins, which defines the `viewLogViewer` gate. This matters: with no gate defined the package falls back to "local environment only", so the explicit definition is what makes access a decision rather than an accident. Gate::before already lets super admins through, so the gate exists to DENY everyone else. tests/Feature/Settings/LogViewerTest.php pins all three outcomes.

Middleware order in config/log-viewer.php is deliberate: 'auth' sits ahead of AuthorizeLogViewer so a signed-out visitor is redirected to login rather than shown a bare 403.

Two operational notes. The package is enabled in production by default (LOG_VIEWER_ENABLED) — the removed custom viewer 404'd there outright, so production logs are now readable by super admins; set LOG_VIEWER_ENABLED=false to restore the stricter stance. And its UI assets live in public/vendor/log-viewer: re-run `php artisan log-viewer:publish --force` after upgrading the package or the page loads without styling.
