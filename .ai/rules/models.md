---
paths:
  - app/Models/EventType.php
  - 'app/Models/Availability*.php'
---

# Models

## Host pool resolution order on event types
Never read `$eventType->hosts` directly to decide who can host. Use `EventType::hostPool()`, which resolves in order:
1. Not a pooled kind (one-on-one, group) -> the owner alone.
2. `group_id` set and the group has members -> the group's members (pivot carries `priority`).
3. Otherwise the hosts pinned to the event type, falling back to the owner.

Both AvailabilityEngine (via schedulingHosts) and AssignHosts go through hostPool(), so a group added to an event type immediately governs availability and round-robin assignment. Saving with a `group_id` clears pinned hosts so there is only ever one answer.

## Wall clock columns are normalised on the model, not by each caller
availability_rules and availability_overrides store starts_at/ends_at as H:i:s, and AvailabilitySchedule::describeHours() parses them with a strict Carbon::createFromFormat('H:i:s', ...). A 5-character "09:00" therefore throws "Not enough data available to satisfy format" and 500s the whole scheduling page.

Inputs disagree on shape: the UI posts H:i (validated as date_format:H:i), Calendly returns H:i, CreateDefaultAvailability uses H:i:s. SQLite stores a `time` column verbatim, so nothing pads it for you — MySQL would have hidden this.

App\Concerns\NormalisesWallClockTimes is on both models and pads on write, so every writer lands H:i:s. Do not go back to per-caller `$value.':00'` concatenation — that is what the Calendly importer missed and it put 121 malformed rows in the database.

Because writes are now normalised, keep describeHours strict; a lenient parser there would just hide the next bad writer.
