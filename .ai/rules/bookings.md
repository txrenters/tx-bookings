---
paths:
  - app/Actions/Bookings/AssignHosts.php
---

# Bookings

## Host assignment is priority-first, and sortBy([...]) is the wrong tool here
Pooled events go to the highest-priority host who is free. The order is: pivot priority (lower number wins), then fewest upcoming bookings, then id. Hosts sharing a priority still load-balance, so a tier of equals spreads evenly and setting no priorities behaves like plain round robin. A busy host simply is not in $availableHostIds, so the next priority down picks the slot up.

Do NOT rewrite this as `->sortBy([fn($host) => ..., ...])`. Laravel's sortByMany treats a callable in that array as a two-argument COMPARATOR ($a, $b), not a key extractor, so the chained-closure form silently compares the wrong values — that was a live bug here before 2026-08. Use the single `->sort(fn ($a, $b) => [...] <=> [...])` comparator.

Priority is read off whichever pivot brought the host into the pool: event_type_hosts for pinned hosts, group_members for a group. Both relations already orderByPivot('priority'). SaveEventType::syncHosts writes priority as the position in the submitted host_ids array, so the order of that array IS the priority order — the UI (HostPriorityList.vue) sends it top-to-bottom.
