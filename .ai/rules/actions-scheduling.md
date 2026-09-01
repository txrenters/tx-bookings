---
paths:
  - 'app/Actions/Scheduling/**'
---

# Actions Scheduling

## Event type slug uniqueness must count soft-deleted rows
The DB unique index on event_types (team_id, slug) is a plain composite index — trashed rows still occupy it, while SaveEventTypeRequest's unique rule deliberately ignores them (whereNull deleted_at) so users get errors only for conflicts they can see. Any code writing an event type slug must therefore go through EventType::generateUniqueSlug(), which checks withTrashed() and suffixes past collisions (new-meeting → new-meeting-2). SaveEventType applies it on both create and update; skipping it reintroduces a 500 (UniqueConstraintViolationException) when a slug matches a deleted event type.
