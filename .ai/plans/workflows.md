# Workflows (automations)

Status: **foundation built, not yet wired up or usable.** Nothing sends. See
"What is left" before continuing.

## Why

TexasRenters runs three Calendly workflows today. All three are email, all fire
when a meeting is booked, and one applies to two event types:

| Name | Applies to | When | Do |
| --- | --- | --- | --- |
| Email reminder to someone else | Leasing Team Meeting | when booked | email someone else |
| Email reminder to someone else - Accounting | Accounting Team Meeting | when booked | email someone else |
| Email reminder to someone else - Leasing and NTM | Leasing Team Meeting, New to Market Meeting | when booked | email someone else |

An earlier screenshot of a different Calendly account also showed SMS to host at
10 and 30 minutes before start. That is deliberately **out of scope** — see
"Decisions".

## Nothing can be imported

Calendly's public API has no workflows endpoint. Checked live against the
account's own token:

```
GET /workflows      -> 404 "The requested path does not exist"
GET /routing_forms  -> 200, 2 items
```

So workflows have to be re-entered by hand. Routing forms *are* exposed (they
have two), if that feature is ever wanted — unrelated to this.

## Data model (built)

`automations` — the rule itself.
- `team_id`, `name`, `is_active`
- `trigger`: `booked` | `before_start` | `after_end`
- `offset_minutes`: optional for `booked` ("24 hours after it is booked"),
  required for the other two
- `recipient`: `host` | `invitee` | `someone`, with `recipient_email` when
  `someone`
- `subject`, `body` — both take variables, see below

`automation_event_type` — which event types it applies to. **No rows means
every event type in the organization**, which is how "applies to all" is said
without a magic value.

`automation_runs` — one row per booking per automation: `send_at`, `sent_at`,
`failure`. Same shape as `booking_reminders`, which is the machinery this
generalises. Unique on (automation, booking) so a re-run cannot duplicate.

## How it fires (partly built)

- `App\Actions\Bookings\ScheduleAutomations` (built): given a booking, finds the
  active automations covering its event type and writes the runs.
  `Automation::sendAtFor()` decides the moment; a "before start" whose moment
  has already passed is skipped rather than sent late.
- Rescheduling re-queues: pending runs are rebuilt, sent runs are left alone, so
  moving a meeting does not resend what already went out.
- Cancelling must delete pending runs. **Not wired yet.**
- Sending: a command picks up due runs the way `bookings:send-reminders` does,
  on the same five-minute schedule. **Not built.**

## Message variables

Calendly's editor offers these, and the body is free text with them inline. Map
them one for one, spelled `{{ ... }}`:

`event_name`, `event_organizer`, `event_date`, `event_time`, `invitee_name`,
`invitee_first_name`, `invitee_last_name`, `invitee_email`, `invitee_phone`,
`location`, `event_description`, `questions_and_answers`

`invitee_phone` comes from the booking's phone question or a call-me location;
it is blank when neither was asked. `questions_and_answers` renders the booking
answers as label/answer lines, the way the reminder email already does.

## UI plan

Two screens, mirroring Calendly's so the team recognises them:

- **List** (Scheduling → Workflows): Name | Applies to | When this happens |
  Do this, plus New workflow. Rows show the event type names, or "All event
  types" when the pivot is empty.
- **Editor**: name, "Which event types will this apply to?" (multi-select),
  "When this happens" (trigger + offset + unit), "Do this" (recipient, address
  when someone else, subject, body, variable list).

Gate on `ManageEventTypes`, which admins and members both hold — the same
permission that governs the event types these attach to.

## Decisions

- **Email only.** All three real workflows are email. SMS needs a provider
  (Twilio), a spend decision, and a phone number on `users`, which does not
  exist — the only phone in the system is the invitee's.
- **One action per workflow.** Calendly allows five; every workflow they
  actually run has one. Multiple actions would be an `automation_actions` table
  hanging off the same rule, if it is ever wanted.
- **No per-recipient templates.** Calendly ships templates ("Custom" etc.); a
  subject and body per workflow is enough to start.
- Existing `booking_reminders` stay as they are. They are a separate, always-on
  mechanism; folding them into automations would change behaviour nobody asked
  to change.

## What is left

1. Wire `ScheduleAutomations` into `CreateBooking` and `RescheduleBooking`, and
   clear pending runs in `CancelBooking`.
2. Render the message: a class that takes a run and produces subject and body
   with the variables filled in, plus the mailable that sends it.
3. `automations:run` command + a `routes/console.php` entry beside
   `bookings:send-reminders`, marking runs sent and recording failures.
4. Controller, form request, routes, and the two Vue screens.
5. Tests: covers/does-not-cover an event type, offsets in both directions, a
   past "before start" skipped, reschedule not resending, cancel clearing,
   variables rendered, and only the organization's own workflows visible.

## Trap worth knowing

`Automation::sendAtFor()` returns null when a timed trigger's moment has already
passed. A meeting booked for this afternoon is already past its "an hour before"
point; queueing it would send a reminder about a meeting that has started. The
`booked` trigger deliberately does not skip, since its moment is now.
