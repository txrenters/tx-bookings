# Workflows (automations)

Status: **built and wired up.** Workflows can be created in the UI, fire on
bookings, and send -- by email, by text message through Twilio, or both. See
"Traps worth knowing" before changing any of it.

## Why

TexasRenters runs three Calendly workflows today. All three are email, all fire
when a meeting is booked, and one applies to two event types:

| Name | Applies to | When | Do |
| --- | --- | --- | --- |
| Email reminder to someone else | Leasing Team Meeting | when booked | email someone else |
| Email reminder to someone else - Accounting | Accounting Team Meeting | when booked | email someone else |
| Email reminder to someone else - Leasing and NTM | Leasing Team Meeting, New to Market Meeting | when booked | email someone else |

An earlier screenshot of a different Calendly account also showed SMS to host at
10 and 30 minutes before start. That shape is now possible: a workflow can send
a text message as well as, or instead of, an email.

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
- `channels`: a list holding `email`, `sms`, or both. One rule, one set of runs,
  however many ways it goes out
- `recipient`: `host` | `invitee` | `someone`. When `someone`, the addresses are
  in `recipient_emails` and the numbers in `recipient_phones`, both lists
- `subject`, `body` — the email, in markdown
- `sms_body` — the text message, plain, capped at 640 characters (four Twilio
  segments)
All of subject, body and sms_body take the variables below.

`automation_event_type` — which event types it applies to. **No rows means
every event type in the organization**, which is how "applies to all" is said
without a magic value.

`automation_runs` — one row per booking per automation: `send_at`, `sent_at`,
`failure`. Same shape as `booking_reminders`, which is the machinery this
generalises. Unique on (automation, booking) so a re-run cannot duplicate.

## How it fires

- `App\Actions\Bookings\ScheduleAutomations`: given a booking, finds the active
  automations covering its event type and writes the runs.
  `Automation::sendAtFor()` decides the moment; a "before start" whose moment
  has already passed is skipped rather than sent late.
- `CreateBooking` and `ApproveBooking` call it, guarded on the booking being
  confirmed — a request awaiting approval gets its runs when a host says yes,
  exactly as reminders do.
- Rescheduling re-queues: pending runs are rebuilt, sent runs are left alone, so
  moving a meeting does not resend what already went out.
- `CancelBooking`, `DeclineBooking` and `RescheduleBooking` delete the pending
  runs of the booking they close.
- `automations:run` picks up due runs the way `bookings:send-reminders` does, on
  the same five-minute schedule in `routes/console.php`. It mails
  `AutomationEmail`, whose subject and body come from
  `App\Services\Automations\AutomationMessage`.

## Text messages

`App\Services\Sms\TwilioClient` talks to Twilio over the Http facade, no SDK,
the way the calendar and mail providers do -- so tests fake it with
`Http::fake()`.

- Credentials are the installation's: `TWILIO_SID` and `TWILIO_AUTH_TOKEN` on
  `services.twilio`. Blank keys mean text messages are simply never sent, which
  is the normal state before anyone signs up for Twilio.
- The number texts come FROM is per organization: `teams.sms_from_number`,
  chosen by an admin in the organization settings from the numbers the account
  actually owns (`IncomingPhoneNumbers.json`, cached ten minutes). Validation
  only accepts a number off that list, so a typo cannot be saved and discovered
  at send time.
- The numbers texts go TO: `users.phone` for hosts (optional, set on the profile
  page), the booking's phone question or call-me location for the invitee
  (`Booking::inviteePhone()`), and `recipient_phones` for someone else.
- Everything is stored in E.164 (`App\Support\PhoneNumber`), because that is
  what Twilio dials; `App\Rules\DialableNumber` keeps anything else out.

Deliberately NOT texted: the always-on booking confirmations, reminders and
cancellations. They stay email. A workflow can already cover each of those
moments if one of them should be texted.

## Message variables

Calendly's editor offers these, and the body is free text with them inline. Map
them one for one, spelled `{{ ... }}`:

`event_name`, `event_organizer`, `event_date`, `event_time`, `invitee_name`,
`invitee_first_name`, `invitee_last_name`, `invitee_email`, `invitee_phone`,
`location`, `event_description`, `questions_and_answers`

`invitee_phone` comes from the booking's phone question or a call-me location;
it is blank when neither was asked. `questions_and_answers` renders the booking
answers as label/answer lines, the way the reminder email already does.

## UI

Two screens, mirroring Calendly's so the team recognises them:

- **List** at `/{team}/automations`, "Workflows" in the sidebar: Name | Applies
  to | When this happens | Do this. Rows show the event type names, or "All
  event types" when the pivot is empty.
- **Editor** at `/{team}/automations/new` and `/{team}/automations/{id}`: name,
  "Which event types will this apply to?" (checkboxes), "When this happens"
  (trigger + offset + unit), "Do this" (email and/or text, recipient, addresses
  and numbers when someone else, subject, message, clickable variable list).
  The email message has a markdown toolbar; the text message has a segment
  count. The variable buttons write into whichever message was last focused.

The model is an `Automation` and the routes are `automations.*`; only the copy
says "workflow", the same way the `Group` model is a "team" in the UI.

Gated on `ManageEventTypes`, which admins and members both hold — the same
permission that governs the event types these attach to.

## Decisions

- **Email and text, in one rule.** Calendly makes each an action of its own; a
  workflow here ticks either or both, so "when someone books, email and text"
  stays one rule with one set of runs behind it. Each channel keeps its own
  wording, because a phone shows markdown as the asterisks it is.
- **One recipient per workflow.** Calendly allows five actions; every workflow
  they actually run has one. More would be an `automation_actions` table hanging
  off the same rule, if it is ever wanted.
- **No per-recipient templates.** Calendly ships templates ("Custom" etc.); a
  subject and body per workflow is enough to start.
- Existing `booking_reminders` stay as they are. They are a separate, always-on
  mechanism; folding them into automations would change behaviour nobody asked
  to change.

## What is left

Nothing for the feature as scoped. Still deliberately out: more than one
recipient per workflow, per-recipient templates, and texting the always-on
booking notifications (see "Decisions"). The three Calendly workflows have to be
typed in by hand once, since nothing can be imported.

Covered by `tests/Feature/Scheduling/WorkflowTest.php`: covers/does-not-cover an
event type, offsets in both directions, a past "before start" skipped,
reschedule not resending, cancel clearing, variables rendered, formatting
surviving into the email, and only the organization's own workflows visible.
`tests/Feature/Sms/TextMessageTest.php` covers the Twilio client, the per
organization number, who gets texted, and the phone number on the profile.

## Traps worth knowing

A reschedule REPLACES the booking row rather than moving it, so what already
sent is recorded against the booking the replacement supersedes.
`ScheduleAutomations::sentAutomationIds()` walks `rescheduled_from_id` up the
chain; without that walk, every move re-announces the meeting.

`RunAutomations` marks a run sent even when the send threw, keeping the reason
in `failure`. The scheduler runs every five minutes, so a bad address would
otherwise be retried forever. A past start is NOT a reason to drop a run the way
it is for reminders — "after the meeting ends" is due precisely then.

A recipient with no phone number is skipped silently; an organization with no
`sms_from_number` fails the run and says so. The difference is deliberate: one
host who has not filled in their profile must not stop the text reaching
everyone else, but a workflow that can never text anybody is a setup mistake
worth surfacing.

The email body goes through a markdown mail view, NOT `MailMessage::line()`:
`line()` flattens the newlines out of a string, which collapses the writer's
paragraphs and lists into one run-on sentence.

`Automation::sendAtFor()` returns null when a timed trigger's moment has already
passed. A meeting booked for this afternoon is already past its "an hour before"
point; queueing it would send a reminder about a meeting that has started. The
`booked` trigger deliberately does not skip, since its moment is now.
