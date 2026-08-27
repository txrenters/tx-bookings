---
paths:
  - 'app/Services/Logs/**'
---

# Logs

## Log viewer is development-only and must stay path-safe
LogViewerController::authorizeViewing() aborts 404 when app()->isProduction(). There is deliberately no role or permission that unlocks it — log files routinely contain tokens, emails and request payloads, so production access is not a permissions question. The `canViewLogs` Inertia prop mirrors the same check for the settings nav.

LogReader::pathFor() is the only way a file name becomes a path: it requires `^[A-Za-z0-9._-]+\.log$`, rejects `..`, and realpath-checks that the result is still inside storage/logs. Every public method goes through it. A test walks `../../.env`, `/etc/passwd` and friends and asserts .env survives — do not bypass this helper.

Only the last 512KB of a file is ever read (LogReader::tail), and the leading partial entry is discarded so a mid-entry offset does not parse as a fragment. Log files reach hundreds of MB; never file_get_contents one.

Routes live in the plain ['auth','verified'] settings group, NOT inside the EnsureTeamMembership group — that middleware needs a {team} route parameter and returns 403 without one.
