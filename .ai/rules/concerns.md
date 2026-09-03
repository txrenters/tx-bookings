---
paths:
  - app/Concerns/HasTeams.php
---

# Concerns

## Super admins bypass membership rather than joining every organization
`users.is_super_admin` grants cross-organization access. A super admin is deliberately NOT a member of anything — team_members stays clean and org rosters never show them. Four places implement the bypass and all four are load-bearing:

- EnsureTeamMembership: skips both the membership check and the minimum-role check.
- HasTeams::switchTeam(): allows switching into an organization they do not belong to.
- HasTeams::toUserTeams(): returns every Team so the switcher lists them all.
- HasTeams::toTeamPermissions(): returns all-true, because teamRole() is null for them and would otherwise deny everything.

Consequence to keep in mind: teamRole() and ownsTeam() still return null/false for a super admin. Any NEW authorization check must ask isSuperAdmin() explicitly — do not assume a role lookup covers them.

The log viewer is super-admin-only in every environment (it used to be a non-production gate). It 404s rather than 403s so the screen's existence stays quiet. Logs carry tokens and PII, so this account is the most sensitive in the app.

Create or promote with `php artisan user:super-admin {email}` (`--revoke` to remove). It verifies the address and assigns a current organization, since a super admin joins none and would otherwise land nowhere after signing in.

## hasTeamPermission() is now the fifth super admin bypass
The "four places implement the bypass" list above is now five. HasTeams::hasTeamPermission() returns true for a super admin before the role lookup, because teamRole() is null for them and would otherwise deny.

This matters beyond policies: EventTypeController::formOptions (canAssignOwner) and MeetingController call hasTeamPermission() DIRECTLY and never reach a policy, so the Gate::before grant in AppServiceProvider cannot cover them. Symptom when it was missing: a super admin creating an event type got the host field locked to themselves and the save failed with "The selected user id is invalid", because SaveEventTypeRequest checks Rule::exists('team_members', ...) and a super admin is deliberately not a member.

Related: CreateEventTypePanel.vue defaults the host to the first real team member rather than the acting user, for the same reason. Do not default a new event type's user_id/host_ids to currentUser without checking they are in teamMembers.
