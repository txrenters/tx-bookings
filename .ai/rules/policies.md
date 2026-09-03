---
paths:
  - 'app/Policies/**'
---

# Policies

## Super admins are granted every ability by Gate::before, not per policy
A super admin belongs to no organization, so teamRole() is null and every role based permission lookup denies them. EnsureTeamMembership lets them REACH any org, but before 2026-08 the policies still returned 403 the moment they tried to act — only TeamPolicy::createMember worked, because it asks isSuperAdmin() explicitly.

AppServiceProvider::grantSuperAdminsEveryAbility() now registers Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null). Do not copy the old per-policy isSuperAdmin() workaround into new policies; the central grant covers them.

The closure MUST return null, not false, for non super admins. Returning false short circuits Gate and denies the entire application. There is a regression test pinning this in tests/Feature/Settings/SuperAdminTest.php.
