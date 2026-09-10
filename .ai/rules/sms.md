---
paths:
  - 'app/Services/Sms/**'
---

# Sms

## Twilio: one account, but the sending number is per organization
TWILIO_SID / TWILIO_AUTH_TOKEN on services.twilio are the installation's; each organization picks which of the account's numbers its texts come from (teams.sms_from_number), and only an admin can, in the organization settings. SaveTeamRequest only accepts a number the account actually owns, so a typo cannot be saved and discovered at send time.

TwilioClient is plain Http facade calls, no SDK — same as the Graph mailer and the calendar providers — so tests use Http::fake(). phpunit.xml blanks both keys, so the suite can never reach the real account; a test that needs Twilio sets the config itself alongside the fake.

Every number is stored in E.164 (App\Support\PhoneNumber), because that is what Twilio dials. Hosts' numbers are users.phone (optional, profile page); the invitee's comes from Booking::inviteePhone(). A recipient with no number is skipped silently, but an organization with no sms_from_number fails the run — one unfilled profile must not stop the text reaching everyone else, while a workflow that can never text anybody is a setup mistake worth surfacing.
