---
paths:
  - app/Jobs/SyncLeavePeriods.php
---

# Jobs

## Leave comes from the Outlook auto-reply, not a calendar
A host counts as on leave when their mailbox has an automatic reply switched on. SyncLeavePeriods reads /me/mailboxSettings/automaticRepliesSetting through the account's own token and caches the result as a leave_periods row (one per calendar account); BusyTimeRepository::forHosts merges those ranges into busy time, so the availability engine and round-robin assignment both skip them with no extra wiring.

Only Microsoft implements DetectsLeaveContract — Google's vacation responder lives behind a Gmail scope the calendar flow deliberately does not ask for, so Google accounts no-op.

Two traps: MailboxSettings.Read was added to MicrosoftCalendarProvider's scopes after accounts already existed, so older tokens get a 403 — the job records that on sync_error (surfaced in the calendar settings UI as a prompt to reconnect) and returns instead of retrying, because only the account owner can grant consent. And an 'alwaysEnabled' reply has no end date, so it blocks to the end of the sync window and each hourly run renews it; do not treat that stored ends_at as a date the user chose.
