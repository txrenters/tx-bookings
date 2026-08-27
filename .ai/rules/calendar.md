---
paths:
  - 'app/Services/Calendar/**'
---

# Calendar

## Calendar providers are plain HTTP, no SDKs
Google Calendar and Microsoft Graph are called through Laravel's HTTP client behind CalendarProviderContract — deliberately no google/apiclient or microsoft-graph SDK, so there is nothing to keep in sync and tests use Http::fake().

Resolve drivers via CalendarProviderManager::for($account), which refreshes an expired access token before returning. Tokens are cast to 'encrypted' on CalendarAccount; never log or expose them.

OAuth redirect URIs must stay stable, so calendar routes live outside the {current_team} prefix at /settings/calendars/{provider}/callback. The callback verifies the session state token before exchanging the code.

Only the provider that owns the meeting type generates a link (Teams from Microsoft, Meet from Google) — see LocationType::provider().
