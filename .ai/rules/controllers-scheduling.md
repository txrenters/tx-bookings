---
paths:
  - app/Http/Controllers/Scheduling/GroupController.php
---

# Controllers Scheduling

## The Group model is "Team" in the UI, one level below the organization
Naming after the 2026-08 rename: the `Team` model is an ORGANIZATION in the UI, and the `Group` model is a TEAM (Leasing, Maintenance) inside it. Do not rename the models — routes, Wayfinder output, policies and the teams/ controllers all key off the current names. Only user-facing copy says organization/team.

A Group is a reusable host pool. GroupController::syncMembers writes `priority` as the position in the submitted member_ids array, exactly like SaveEventType::syncHosts, so the order in the UI IS the booking order. Group::members() already orderByPivot('priority').

Point an event type at a group and every member hosts it: EventType::hostPool() prefers the group over pinned hosts, and SaveEventType clears pinned hosts whenever group_id is set so there is only ever one answer. Editing the group therefore changes every event type pointed at it — that propagation is the feature, and there is a test for it.

The page is at /{current_team}/groups and reached from the sidebar's "Teams" entry. It had no navigation entry at all until 2026-08; if you add a scheduling page, link it.
