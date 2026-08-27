---
paths:
  - 'app/Services/Calendly/**'
  - app/Services/Calendly/CalendlyClient.php
---

# Calendly

## Calendly import must stay silent and idempotent
The importer writes through models directly and never through the booking actions (CreateBooking / NotifyBookingParties). Those actions email invitees and push to connected calendars — an import of meetings that were already arranged in Calendly must not do either. A test asserts Notification::assertNothingSent() over a full run; keep it.

Idempotency lives in the `calendly_imports` table: one row per Calendly URI, morphing to whatever local record it became. Re-running updates in place instead of duplicating. Do not add `calendly_*` columns to the domain tables — that mapping table is deliberately the only vendor-aware schema.

Plain Http:: calls, no SDK, matching the Google/Microsoft calendar providers. Pagination follows `pagination.next_page_token` and stops if the token repeats, because a malformed page otherwise loops forever.

Resource order in CalendlyImporter::RESOURCES is a dependency order (members → event_types → schedules → bookings); `--only` is re-sorted into it. Bookings are skipped when their event type was not imported, since a Booking needs a local event_type_id.

Known gaps worth stating to a user rather than silently dropping: AdhocEventType (one-off meeting polls) has no local equivalent, Calendly exposes a single location per event type where we also store one, and advanced availability rules beyond weekly intervals + date overrides do not come across.

## What Calendly's API will and will not give you
The env key is CALENDLY_API_KEY (config services.calendly.token). Do not rename it back to _TOKEN.

Round robin / collective hosts are NOT on the event type payload. They come from GET /event_type_memberships?event_type={uri} — one extra call per pooled event type — and the person is under `member`, not `user`. That tripped the first implementation.

Availability is the real limitation. /user_availability_schedules returns named schedules with weekday rules, but an event type's payload has no reference to which schedule it uses, and per-event-type "custom hours" are not exposed at all. If the named schedules have empty intervals (as the TexasRenters account does — 7 weekday rules, zero intervals on both), nothing about working hours can be imported and slots must be set up by hand here. Do not treat empty rules as an importer bug; check the stored payload in calendly_imports first, which is why the raw payload is kept.

custom_questions IS on the event type payload. Calendly's "text" is a multi-line box (maps to Textarea) and "string" is single-line (maps to Text) — not the other way round.

Tests must end their Http::fake array with a '*' catch-all so an unstubbed endpoint fails loudly instead of hitting the real API with a live token.
