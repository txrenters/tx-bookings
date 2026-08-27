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
