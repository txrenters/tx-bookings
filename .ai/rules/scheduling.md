---
paths:
  - 'app/Services/Scheduling/**'
---

# Scheduling

## Availability engine: how slots are computed
Slot generation is layered and each layer has one job:
- WorkingHoursCalculator expands a schedule's weekly rules + date overrides into UTC windows. Rules are wall-clock times in the schedule's own timezone, so always expand per local day, then convert to UTC. An override for a date REPLACES that day's rules.
- BusyTimeRepository merges internal bookings (padded by the event type's buffers) with synced busy_blocks. Only calendars with checks_conflicts = true block time.
- AvailabilityEngine subtracts busy from working windows, then steps by slot interval.

Kind matters: round robin/one-on-one take the UNION of hosts' free time (slot keeps the list of hosts free for it); collective takes the INTERSECTION. Seats only gate group events — for every other kind a booked host is already removed from the slot's host list, so do not also filter on per-start booking counts (that bug hid round-robin slots another host could still take).

Always store timestamps in UTC; convert at the edges only.
