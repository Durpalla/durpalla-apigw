# Active context — durpalla-apigw

## Current focus

- Memory bank established (2026-09-18)
- Preserve zero-wipe policy for API sources and schema-in-durpalla ownership

## Recent decisions

- Agents read/update `memory-bank/` for requirements
- Existing `.cursor/rules/*` retained unchanged (additive `memory-bank.mdc` only)

## 2026-09-23

- Forgot-password OTP: `auth/forgot` refreshes `user_otps.updated_at` on every send. Verify expires a forgot-password code after 5 minutes (other OTP types stay at 15). A reused dev code (`111111`) used to leave `updated_at` stale, so verify always returned expired.

## Open notes

- When adding hotel/transport fields, migrate in **durpalla**, then update services/resources here
- Extra-guest / localization docs exist under `docs/` — consult when touching guest pricing or i18n extras
## 2026-09-18

- Entertainment APIs: customer/merchant/agent hold-confirm-redeem-override; expire-tickets command.
