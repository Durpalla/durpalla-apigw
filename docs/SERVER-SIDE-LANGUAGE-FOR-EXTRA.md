# Durpalla — One-Shot Dynamic Localization Implementation Plan

## Objective

Implement a production-ready multilingual localization system across the Durpalla applications, including the React customer panel (`durpalla-web`).

Every API response `message` (success, error, validation) must be returned in the locale resolved from request headers. Fallback is always English (`en`).

### Bundled languages — always available offline

- English (`en`) — DEFAULT
- Bangla (`bn`) — DEFAULT

These two languages must remain bundled inside each client application.

### Server-side languages — downloaded on demand

- Hindi (`hi`)
- Arabic (`ar`)
- Chinese (`zh`)
- Urdu (`ur`)
- Farsi/Persian (`fa`)
- Turkish (`tr`)
- Spanish (`es`)
- Italian (`it`)

Do **not** bundle these additional languages into the initial Flutter application unless the existing architecture makes this unavoidable. They must be downloaded dynamically from the API Gateway and cached locally.

---

# Applications

## Server

Application:

`durpalla-apigw`

Base URL:

`https://apigw.durpalla.com`

The API Gateway is responsible for serving localization resources.

## Clients

Implement/update localization support in all four clients:

1. `durpalla-web` — React / Next.js customer panel
2. `durpalla-web-merchant` — React merchant panel
3. `durpalla-flutter-merchant-desk`
4. `durpalla-flutter-app`

All four clients should send `Content-Language: {locale}` on every API request when the active UI locale is known. Browsers also send `Accept-Language` by default. The gateway resolves API message locale from those headers (section 11A).

---

# Core Requirements

## 1. English is the global fallback

The localization system must always have a valid fallback.

Fallback priority:

```text
Requested language
        ↓
Cached requested language
        ↓
Bundled English
```

English (`en`) is the ultimate fallback.

Never display a missing translation key such as:

```text
translation.some_key
```

when an English translation exists.

---

# 2. English and Bangla remain bundled

The following must be available immediately after installation/build:

```text
en
bn
```

They must work without an internet connection.

The application must start and remain usable offline using either of these languages.

Do not move English or Bangla to server-only localization.

---

# 3. Additional languages are server-side

The following languages must NOT be included in the default Flutter application localization bundle:

```text
hi
ar
zh
ur
fa
tr
es
it
```

When the user selects one of these languages:

```text
Flutter/Web Client
       ↓
Check local cache
       ↓
If cached and current → use cache
       ↓
Otherwise
       ↓
GET localization from
https://apigw.durpalla.com
       ↓
Validate response
       ↓
Save locally
       ↓
Activate language
```

---

# 4. No paid translation API

Do not integrate:

- Google Translate API
- DeepL API
- OpenAI API for runtime translation
- Any other paid translation service

Translations are maintained as application-managed localization resources.

The API Gateway only distributes the prepared translations.

---

# 5. Translation source of truth

The server-side localization resources should have a clear source-of-truth structure.

Prefer a structure compatible with the existing project localization conventions.

Example:

```text
locales/
├── en/
├── bn/
├── hi/
├── ar/
├── zh/
├── ur/
├── fa/
├── tr/
├── es/
└── it/
```

Do not blindly create a parallel localization architecture if one already exists.

First inspect each repository and reuse its existing localization conventions where practical.

---

# Server: durpalla-apigw

## 6. Add localization API endpoints

Implement a public/read-only endpoint for retrieving translations.

Preferred endpoint:

```http
GET /api/v1/localizations/{locale}
```

Example:

```http
GET https://apigw.durpalla.com/api/v1/localizations/hi
```

If the existing API versioning/routing convention differs, follow the existing convention rather than introducing an inconsistent route.

The endpoint must:

1. Validate locale.
2. Return only supported locales.
3. Return the requested translation resource.
4. Include a version/hash for cache validation.
5. Return appropriate cache headers where safe.
6. Never expose secrets or internal filesystem paths.

---

# 7. Supported locale configuration

Create a single authoritative supported-locale configuration.

Example conceptual structure:

```text
en → English
bn → বাংলা
hi → हिन्दी
ar → العربية
zh → 中文
ur → اردو
fa → فارسی
tr → Türkçe
es → Español
it → Italiano
```

Use standard locale identifiers consistently.

Recommended identifiers:

```text
en
bn
hi
ar
zh
ur
fa
tr
es
it
```

Do not use inconsistent variants such as:

```text
tur
ind
ara
per
```

unless an existing project requirement explicitly requires them.

---

# 8. Translation response format

Use a stable JSON structure.

Preferred response:

```json
{
  "locale": "hi",
  "version": 1,
  "fallback_locale": "en",
  "translations": {
    "home": "होम",
    "booking": "बुकिंग",
    "cancel": "रद्द करें"
  }
}
```

The exact field names may be adapted to an existing API convention, but the response must provide:

- locale
- version
- fallback locale
- translation dictionary

Do not return unnecessary application data.

---

# 9. Versioning / cache invalidation

The server must support translation updates without requiring a new client release.

Each locale must have a version or content hash.

Example:

```json
{
  "locale": "hi",
  "version": 7,
  "translations": {}
}
```

Recommended behavior:

```text
Client cached version = 7
Server version = 7
        ↓
No download required

Client cached version = 6
Server version = 7
        ↓
Download latest translation
```

If practical with the existing API architecture, support HTTP:

```text
ETag
Cache-Control
Last-Modified
```

Do not introduce unnecessary complexity if the API already has an established caching mechanism.

---

# 10. Server-side caching

Localization files are mostly static and should be aggressively cacheable.

Use the existing infrastructure where available:

```text
Laravel cache
Redis
Cloudflare
HTTP cache headers
```

Do not perform database queries for every localization request if the translations can be loaded from a file/config/cache.

If translations are stored in the database, cache the complete locale dictionary.

---

# 11. Security

Localization endpoints contain public UI strings and should not require authentication unless the existing architecture mandates authenticated API access.

Never place:

- API keys
- credentials
- private configuration
- user data
- internal filesystem paths

inside translation files.

Validate the locale against an allow-list.

Do not allow arbitrary filesystem path access through `{locale}`.

---

# 11A. API response messages follow request locale

UI dictionaries (section 6–10) and API response messages are two different localization surfaces. Both must honor the same locale allow-list.

Clients may send one or both headers. `durpalla-flutter-app` already sends `Content-Language` on every request (`lib/services/api_service.dart`). Web browsers send `Accept-Language` by default. Extend both conventions across all clients and every API message.

## Request contract

Locale resolution priority:

```text
1. Accept-Language   (if present and non-empty)
2. Content-Language  (if Accept-Language is missing or empty)
3. en                (fallback)
```

Example headers:

```http
Accept-Language: hi-IN,hi;q=0.9,en;q=0.8
Content-Language: hi
```

Rules:

```text
1. Value must map to a supported locale from the allow-list
   (en, bn, hi, ar, zh, ur, fa, tr, es, it)
2. Normalize regional tags: en-US → en, bn-BD → bn, zh-CN → zh
3. For Accept-Language, take the first supported language tag
   (respect q-values when practical; at minimum parse the first tag)
4. Unsupported value after normalization → try the next header in the chain
5. Both missing, empty, or unsupported → en
```

Resolution:

```text
Accept-Language present?
   ├── YES → parse first supported tag
   │              ↓
   │         Normalize (strip region)
   │              ↓
   │         In allow-list?
   │            ├── YES → app()->setLocale(that)
   │            └── NO  → try Content-Language, else en
   └── NO
        ↓
Content-Language present?
   ├── YES → normalize → allow-list → locale or en
   └── NO  → app()->setLocale(en)
```

## Response contract

The API must:

1. Set `app()->setLocale($resolved)` for the request lifecycle.
2. Echo the resolved locale on the response:

```http
Content-Language: hi
```

3. Translate every user-facing `message` field through Laravel `__()` / `trans()`.
4. Translate validation error strings (`errors.*`, `message` first error).
5. Fall back to English when a translation key is missing.

User-facing messages include:

```text
success.message
error.message
validation errors
auth / guest / cart / booking / payment failures
FormRequest messages
JSON exception messages returned to clients
```

Do **not** translate:

```text
technical error codes
stack traces
internal exception class names
IDs, PNR, emails, phone numbers
raw payload field names
filesystem paths
```

## Server implementation

Add one API middleware (name it to match existing apigw conventions), registered on all `/api/*` routes.

Responsibilities:

```text
Read Accept-Language (first)
If absent, read Content-Language
Normalize
Allow-list
app()->setLocale()
Add Content-Language on the response with the resolved locale
```

Reuse the same locale allow-list as the localization endpoints (section 7). Do not create a second locale list.

Reuse the parsing approach already used in `app/Support/BookingInvoice.php` (`resolveLang`) where practical — same normalization and tag parsing, extended to all 10 supported locales.

Translation files live in `durpalla-apigw` using Laravel conventions. Prefer JSON translations keyed by the current English source string, because many controllers already call `__('Your item has been added to cart')`:

```text
lang/en.json   (optional; English source is the key)
lang/bn.json
lang/hi.json
lang/ar.json
lang/zh.json
lang/ur.json
lang/fa.json
lang/tr.json
lang/es.json
lang/it.json
```

Also provide `lang/{locale}/validation.php` (or Laravel JSON validation keys) so FormRequest / validator messages follow the resolved locale.

Existing invoice files (`lang/en/invoice.php`, `lang/bn/invoice.php`) stay as-is. Extend that pattern rather than inventing a parallel store.

Hardcoded English strings that are returned to clients without `__()` must be wrapped. Do not rewrite business logic while doing this.

Missing-key fallback for API messages:

```text
Resolved request locale
        ↓
English source string
```

Never return a raw key such as `api.cart.locked` when an English sentence exists.

## Client implementation

| Client | Current state | Required change |
|---|---|---|
| `durpalla-flutter-app` | Already sends `Content-Language` (`en` / `bn` only) | Accept all 10 locales in `setContentLanguage`. Sync with the active UI locale. Flutter does not send `Accept-Language` by default, so `Content-Language` is the effective header. |
| `durpalla-flutter-merchant-desk` | Does not send the header | Add `Content-Language` on the shared HTTP client. Sync with the active UI locale. |
| `durpalla-web-merchant` | Does not send the header | Add `Content-Language` on the shared Axios/fetch client from `i18n.resolvedLanguage`. Browser `Accept-Language` is used when present; send `Content-Language` so explicit in-app language selection is honored when `Accept-Language` is absent. |
| `durpalla-web` | No i18n; `lib/api.ts` has no language header | After locale is introduced, add `Content-Language` in `requestHeaders()`. Browser `Accept-Language` applies on first load; `Content-Language` covers explicit selection and non-browser clients. |

When the user switches language in the app:

```text
Update UI locale
        ↓
Persist locale
        ↓
Set Content-Language on the HTTP client
        ↓
All subsequent API messages use the resolved locale
```

Do not send a stale `Content-Language` after a language switch.

Web clients: when the user picks a language in-app, set **both** `Accept-Language` and `Content-Language` on the HTTP client to that locale. Browsers send `Accept-Language` by default; if only `Content-Language` is updated, the gateway still reads `Accept-Language` first and may keep responding in the browser default until both headers match.

## Isolation

This middleware must not change authentication, booking, payment, or payload schemas. Only locale + translated `message` / validation strings change.

---

# Flutter clients

Apply the same architecture to:

- `durpalla-flutter-app`
- `durpalla-flutter-merchant-desk`

---

# 12. Flutter localization architecture

First inspect the current localization implementation.

Determine whether the project currently uses:

- Flutter `gen_l10n`
- ARB files
- custom JSON localization
- GetX translations
- Easy Localization
- another localization package
- a custom localization service

Do not replace a working localization framework unnecessarily.

Integrate dynamic server translations into the existing architecture.

---

# 13. Flutter bundled translations

Keep:

```text
en
bn
```

inside the application bundle.

They must be available before any network request.

The application must not block startup while downloading a remote language.

---

# 14. Flutter remote translations

For:

```text
hi
ar
zh
ur
fa
tr
es
it
```

implement:

```text
RemoteLocalizationService
```

or an equivalent service following the project's existing architecture.

Responsibilities:

1. Check local cache.
2. Determine whether cached translation is valid.
3. Download the locale when necessary.
4. Validate the response.
5. Persist it locally.
6. Expose translations to the localization layer.
7. Fall back to English when necessary.
8. Handle offline mode.
9. Handle server errors gracefully.
10. Keep the API client's `Content-Language` header in sync with the active UI locale (all 10 codes, not only `en`/`bn`).

---

# 15. Local cache

Use the storage mechanism already used by the application.

If no suitable mechanism exists, use an appropriate lightweight persistent storage solution already compatible with the project.

Do not add a large dependency solely for localization caching.

Cache structure should conceptually be:

```text
localization/
    hi/
        version
        translations
    ar/
        version
        translations
    zh/
        version
        translations
    ...
```

The actual implementation should follow the project's architecture.

---

# 16. Offline behavior

Required behavior:

### English

Works offline.

### Bangla

Works offline.

### Previously downloaded server language

Works offline using the cached translation.

### Never downloaded server language

Fall back to English.

Example:

```text
User selects Arabic
        ↓
Arabic cached?
   ├── YES → use cached Arabic
   └── NO
        ↓
Internet available?
   ├── YES → download Arabic
   └── NO → use English
```

Do not make the application unusable because a remote language cannot be downloaded.

---

# 17. Language switching

Language switching must happen without requiring:

- application restart
- reinstall
- new APK
- forced logout

Unless the existing application architecture technically requires a restart.

Preferred behavior:

```text
User selects language
        ↓
Load language
        ↓
Update locale
        ↓
Rebuild UI
```

The currently selected locale should persist across application restarts.

---

# 18. First-load UX

When selecting a server-side language for the first time:

```text
User selects Hindi
        ↓
Show loading state
        ↓
Download Hindi
        ↓
Cache Hindi
        ↓
Switch UI to Hindi
```

Avoid a blank screen.

If download fails:

```text
Show a user-friendly error
Keep current language active
```

Do not switch to a partially downloaded translation.

---

# 19. Atomic cache writes

Do not overwrite a valid cached localization with a broken/partial download.

Preferred flow:

```text
Download temporary data
        ↓
Validate JSON
        ↓
Validate locale
        ↓
Validate required structure
        ↓
Write atomically
        ↓
Replace old cache
```

If validation fails, keep the previous valid cache.

---

# 20. Translation key consistency

All clients should use the same logical translation keys where the same UI concept exists.

Example:

```text
common.cancel
common.confirm
common.retry
common.loading

navigation.home
navigation.booking
navigation.trips
navigation.profile

booking.search
booking.confirm
booking.cancel
booking.payment
```

Do not create unnecessary duplicate keys such as:

```text
cancel
cancel_button
cancelBookingButton
booking_cancel
```

when they represent the same global concept.

Follow the existing project's naming convention if one already exists.

---

# 21. Missing-key fallback

If a remote translation is missing a key:

```text
remote locale
      ↓
English bundled translation
```

Example:

```text
hi:
  home: "होम"

en:
  home: "Home"
  booking: "Booking"
```

For Hindi:

```text
home    → होम
booking → Booking
```

Do not display the raw translation key.

---

# 22. Locale-specific UI behavior

Pay special attention to:

### Arabic / Urdu / Farsi

These are RTL languages.

The application must correctly handle:

```text
Text direction
Row direction
Icons where direction matters
Back/forward arrows
Padding/margins
Navigation
Dialogs
Forms
```

Use Flutter's normal `Directionality`/locale behavior rather than manually reversing the entire UI.

Test:

```text
ar
ur
fa
```

### Chinese

Ensure Unicode rendering works correctly.

### Hindi

Ensure Devanagari rendering works correctly.

### Turkish / Spanish / Italian

Check accented characters and text expansion.

---

# 23. Web applications

Apply the same language model to both React panels:

- `durpalla-web` — Next.js customer site (currently English-only, no i18n)
- `durpalla-web-merchant` — Vite merchant panel (already uses `react-i18next`)

Both must implement:

```text
Bundled:
en
bn

Remote:
hi
ar
zh
ur
fa
tr
es
it
```

Remote languages should be lazy-loaded.

Do not load all remote language dictionaries into the initial JavaScript bundle.

Preferred:

```text
Initial page
    ↓
en + bn only

User selects Spanish
    ↓
GET /api/v1/localizations/es
    ↓
Cache
    ↓
Apply Spanish
```

Use each project's existing localization architecture.

- `durpalla-web-merchant`: extend `react-i18next` / `@/i18n`. Do not add a second i18n library.
- `durpalla-web`: no i18n exists today (`<html lang="en">` is hardcoded; `lib/api.ts` has no language header). Inspect first, then add the smallest fit that matches this plan. Prefer reuse of the merchant pattern (`react-i18next`) unless Next.js App Router requires a different official approach. Do not add a second i18n library after one is chosen.

Do not load all remote language dictionaries into the initial JavaScript bundle of either app.

Both web clients must send `Content-Language` on every API request (section 11A).

---

# 23A. Web customer application (`durpalla-web`)

`durpalla-web` is the public React / Next.js customer panel. It is in scope for this plan.

Current facts to preserve while integrating:

```text
Next.js App Router
Shared Axios client: lib/api.ts
requestHeaders() already sets Accept, Authorization, X-Guest-Id
No language switcher
No translation files
html lang is hardcoded to "en"
```

Required work (in addition to section 23):

1. Bundle `en` and `bn` in the app. Keep them available without a network call.
2. Lazy-load the eight remote locales from `GET /api/v1/localizations/{locale}`.
3. Persist selected locale (same storage rules as section 24).
4. Language switcher in the existing header/shell, showing native names (section 27).
5. Set `<html lang>` and `dir` (`ltr` / `rtl`) from the active locale. RTL for `ar`, `ur`, `fa`.
6. Add `Content-Language` to `requestHeaders()` in `lib/api.ts`, always matching the active UI locale.
7. After a language change, subsequent `apiGet` / `apiPost` calls must use the new header immediately.
8. Translate user-visible UI strings (header, search, booking, hotels, checkout, profile, auth, empty/error states).
9. Display API `message` fields as returned — they are already localized by the gateway. Do not re-translate API messages on the client.
10. Reuse existing components, `pageShellClass`, and design tokens. Do not invent a parallel layout or a new HTTP client.

`durpalla-web` is a customer booking site, not a merchant dashboard. QA the actual customer flows (home, search, cart, checkout, bookings, hotels, profile), not merchant screens.

---

# 24. Web caching

Use browser storage appropriate to the existing application:

```text
localStorage
IndexedDB
existing application cache
```

Choose the smallest appropriate solution.

Persist:

```text
locale
translation version
translation dictionary
```

The browser must not repeatedly download the same translation on every page refresh.

---

# 25. Shared API contract

All clients must consume the same localization API.

Example:

```text
GET https://apigw.durpalla.com/api/v1/localizations/en
GET https://apigw.durpalla.com/api/v1/localizations/bn
GET https://apigw.durpalla.com/api/v1/localizations/hi
GET https://apigw.durpalla.com/api/v1/localizations/ar
GET https://apigw.durpalla.com/api/v1/localizations/zh
GET https://apigw.durpalla.com/api/v1/localizations/ur
GET https://apigw.durpalla.com/api/v1/localizations/fa
GET https://apigw.durpalla.com/api/v1/localizations/tr
GET https://apigw.durpalla.com/api/v1/localizations/es
GET https://apigw.durpalla.com/api/v1/localizations/it
```

Clients should not implement separate translation APIs.

Every request to any existing API (not only the localization endpoints) should also send:

```http
Content-Language: {active_locale}
```

Browsers send `Accept-Language` by default. The gateway resolves locale as:

```text
Accept-Language (if present) → Content-Language (if not) → en
```

See section 11A.

---

# 26. Language metadata endpoint

If useful to the existing architecture, provide:

```http
GET /api/v1/localizations
```

Example:

```json
{
  "default_locale": "en",
  "bundled_locales": ["en", "bn"],
  "remote_locales": [
    {
      "code": "hi",
      "name": "Hindi",
      "native_name": "हिन्दी",
      "direction": "ltr",
      "version": 1
    },
    {
      "code": "ar",
      "name": "Arabic",
      "native_name": "العربية",
      "direction": "rtl",
      "version": 1
    }
  ]
}
```

If a static configuration in the client is sufficient, do not create an unnecessary endpoint.

---

# 27. Language selector

The language selector should show native names where possible.

Recommended:

```text
English
বাংলা
हिन्दी
العربية
中文
اردو
فارسی
Türkçe
Español
Italiano
```

Do not display only:

```text
en
bn
hi
ar
...
```

unless the current UI design specifically requires codes.

---

# 28. Initial locale

Default locale:

```text
en
```

Do not automatically select a server-side language merely because the device/browser uses that language.

Recommended behavior:

```text
First launch → English

User manually selects another language
        ↓
Persist selection
        ↓
Use it on future launches
```

If the application already has an explicit device-locale detection policy, preserve it only if it does not conflict with the requirement that English is the default.

---

# 29. Error handling

Remote localization failures must never crash the application.

Handle:

```text
HTTP 404
HTTP 429
HTTP 500
timeout
DNS/network failure
invalid JSON
invalid locale
invalid translation schema
empty translation response
```

Fallback behavior:

```text
Valid cache → use cache
No valid cache → English
```

Log useful diagnostics in development/monitoring without exposing sensitive information.

---

# 30. API performance

Localization files are small and mostly static.

Avoid unnecessary Laravel application overhead.

Recommended:

```text
Client
  ↓
Cloudflare/cache
  ↓
API Gateway
  ↓
Cached localization resource
```

Do not query MySQL for every request.

Redis may be used if it is already part of the API Gateway caching architecture.

---

# 31. Do not duplicate translations

There should be one authoritative translation resource per locale on the server.

Avoid:

```text
Flutter translation
Web translation
Merchant translation
API translation
```

with independently maintained copies of the same server-side language.

Instead:

```text
API Gateway
    ↓
Translation resources
    ↓
Flutter App
Flutter Merchant Desk
Web Merchant
Web Customer (durpalla-web)
```

API response messages (`message`, validation errors) are a second resource set in `durpalla-apigw` `lang/`. They are not client UI dictionaries. Clients display those strings as returned.

English and Bangla may remain bundled in each client for offline/startup requirements.

---

# 32. API backward compatibility

Do not break existing API endpoints.

Localization changes must be isolated from:

- authentication
- booking
- payment
- merchant
- customer
- trip
- ticketing
- other existing APIs

Run the existing test suite before and after the implementation.

---

# 33. Implementation workflow for Cursor Agent

Execute the task in this order.

## Phase 1 — Inspect

Inspect all repositories:

```text
durpalla-apigw
durpalla-web
durpalla-web-merchant
durpalla-flutter-merchant-desk
durpalla-flutter-app
```

Identify:

- framework versions
- existing localization implementation
- translation files
- locale handling
- state management
- HTTP client
- local persistence
- routing
- web storage
- API versioning
- caching
- tests

Do not start modifying files until the existing localization architecture is understood.

---

## Phase 2 — Design

Create a concise implementation plan based on the actual repositories.

Preserve existing architecture whenever practical.

Avoid introducing dependencies when an existing dependency can perform the required work.

---

## Phase 3 — Server implementation

Implement:

- supported locales
- translation resources (UI dictionaries + API message `lang/*.json`)
- localization endpoint
- `Content-Language` API locale middleware (section 11A)
- wrap remaining client-facing hardcoded messages with `__()`
- validation locale files
- versioning/hash
- cache headers/caching
- tests

---

## Phase 4 — Flutter implementation

Implement in both Flutter clients:

```text
durpalla-flutter-app
durpalla-flutter-merchant-desk
```

Ensure:

- en/bn bundled
- remote locales lazy-loaded
- local cache
- version checking
- offline fallback
- language persistence
- runtime switching
- RTL
- missing-key fallback
- error handling
- `Content-Language` on every API request, synced to the active locale (all 10 codes)

---

## Phase 5 — Web implementation

Implement in both:

```text
durpalla-web
durpalla-web-merchant
```

Ensure:

- en/bn bundled
- remote locale lazy loading
- browser caching
- version checking
- runtime switching
- persistence
- RTL
- missing-key fallback
- `Content-Language` on every API request, synced to the active locale
- `durpalla-web`: `<html lang>` / `dir` updated; language switcher in the existing header; `lib/api.ts` sends the header
- `durpalla-web-merchant`: extend existing `react-i18next` / Axios client; do not add a second i18n library

---

## Phase 6 — Translation coverage

Audit all user-visible localization keys.

Ensure all currently localized English strings have corresponding translations for:

```text
bn
hi
ar
zh
ur
fa
tr
es
it
```

Do not silently invent translations for domain-specific terminology if the repository already contains an approved terminology convention.

For ambiguous business terminology, inspect existing product wording and keep terminology consistent.

---

# 34. Translation quality

Use correct native-language UI terminology.

Pay special attention to Durpalla-specific terms such as:

```text
Booking
Trip
Ticket
Passenger
Merchant
Agent
Commission
Payment
Refund
Seat
Route
Departure
Arrival
Pickup
Drop-off
Hotel
Tour
Visa
Transport
```

Do not perform literal word-for-word translations when they create unnatural UI language.

---

# 35. Tests

Add/update automated tests where each project supports them.

## Server tests

Test:

```text
GET supported locale → 200
GET unsupported locale → 404/422 according to API convention
response schema
version
translation dictionary
cache behavior
Accept-Language: hi → API message in Hindi
Accept-Language absent, Content-Language: hi → API message in Hindi
both absent → fallback English
Accept-Language: xx → try Content-Language, else en
Accept-Language: en-US → normalize to en
response echoes Content-Language with the resolved locale
validation errors follow resolved locale
```

## Flutter tests

Test:

```text
English available offline
Bangla available offline
Remote language download
Remote language cache
Cached language loading
Version update
Network failure
Invalid response
Missing translation key
Fallback to English
Locale persistence
RTL locale
Content-Language header matches active locale
```

## Web tests

Run equivalent coverage in both `durpalla-web` and `durpalla-web-merchant`:

```text
English startup
Bangla startup
Remote language lazy loading
Browser cache
Offline cached language
Fallback to English
Language persistence
RTL
Content-Language header matches active locale
html lang / dir updated (durpalla-web)
```

---

# 36. Manual QA matrix

Verify at minimum:

| Locale | Language | Direction |
|---|---|---|
| en | English | LTR |
| bn | Bangla | LTR |
| hi | Hindi | LTR |
| ar | Arabic | RTL |
| zh | Chinese | LTR |
| ur | Urdu | RTL |
| fa | Farsi/Persian | RTL |
| tr | Turkish | LTR |
| es | Spanish | LTR |
| it | Italian | LTR |

Test these screens/flows:

```text
Login
Home
Search
Booking
Trip details
Passenger details
Payment
Booking confirmation
Profile
Settings
Notifications
Merchant dashboard
Customer web: home, search, cart, checkout, hotels, bookings, profile
```

Use the actual screens that exist in each repository.

Also verify API messages (toast / alert / form errors) follow `Accept-Language` when sent, fall back to `Content-Language` when `Accept-Language` is absent, and default to English when both are absent.

---

# 37. App size requirement

The implementation must preserve the main objective:

```text
Initial application package:
    English + Bangla
    + normal application code/assets

NOT:
    English + Bangla + Hindi + Arabic + Chinese
    + Urdu + Farsi + Turkish + Spanish + Italian
```

Remote translation files must not be accidentally bundled into the Flutter release asset tree.

After implementation, inspect the build configuration and generated artifacts to confirm this.

---

# 38. Network efficiency

Do not download a remote language more than necessary.

Required behavior:

```text
First selection:
    download

Next launch:
    load cache

Later launch:
    check version efficiently

Version unchanged:
    do not download full dictionary

Version changed:
    download updated dictionary
```

If the existing backend supports ETag/conditional requests, prefer that mechanism.

---

# 39. Do not over-engineer

Do NOT introduce:

- microservices
- a translation management SaaS
- paid translation APIs
- a separate localization database unless necessary
- a new cache server
- unnecessary packages
- a new state-management framework
- a new HTTP framework

Reuse existing Durpalla infrastructure.

---

# 40. Final acceptance criteria

The task is complete only when all of the following are true.

### Server

- [ ] `durpalla-apigw` serves all 10 locales.
- [ ] Locale allow-list exists.
- [ ] Translation API is implemented.
- [ ] Version/hash exists.
- [ ] Cache strategy exists.
- [ ] Invalid locale is safely rejected.
- [ ] Locale middleware resolves `Accept-Language` first, then `Content-Language`, then `en`.
- [ ] Missing / invalid headers fall back through the chain to `en`.
- [ ] Regional tags normalize (`en-US` → `en`).
- [ ] User-facing API `message` and validation errors are translated.
- [ ] Response echoes `Content-Language` with the resolved locale.
- [ ] No database query is performed per request when avoidable.
- [ ] Automated tests pass.

### Flutter App

- [ ] `en` bundled.
- [ ] `bn` bundled.
- [ ] `hi` remote.
- [ ] `ar` remote.
- [ ] `zh` remote.
- [ ] `ur` remote.
- [ ] `fa` remote.
- [ ] `tr` remote.
- [ ] `es` remote.
- [ ] `it` remote.
- [ ] Remote languages cached locally.
- [ ] Offline cached language works.
- [ ] Uncached remote language falls back to English offline.
- [ ] Language persists.
- [ ] Runtime language switching works.
- [ ] Missing keys fall back to English.
- [ ] RTL works for Arabic, Urdu and Farsi.
- [ ] Remote translation files are not bundled into the initial APK/AAB.
- [ ] Tests pass.
- [ ] `Content-Language` is sent on every API request and matches the active locale (all 10 codes, not only `en`/`bn`).

### Flutter Merchant Desk

- [ ] Same requirements as Flutter App.
- [ ] Uses the same API contract.
- [ ] Remote translations are not bundled.
- [ ] Tests pass.
- [ ] `Content-Language` is sent on every API request and matches the active locale.

### Web Merchant

- [ ] `en` bundled.
- [ ] `bn` bundled.
- [ ] Eight additional languages are lazy-loaded.
- [ ] Browser caching works.
- [ ] Language persists.
- [ ] Offline cached language works.
- [ ] Missing keys fall back to English.
- [ ] RTL works.
- [ ] Remote language dictionaries are not included in the initial JS bundle.
- [ ] Tests/build pass.
- [ ] `Content-Language` is sent on every API request and matches the active locale.

### Web Customer (`durpalla-web`)

- [ ] `en` bundled.
- [ ] `bn` bundled.
- [ ] Eight additional languages are lazy-loaded.
- [ ] Browser caching works.
- [ ] Language persists.
- [ ] Offline cached language works.
- [ ] Missing keys fall back to English.
- [ ] RTL works (`ar`, `ur`, `fa`); `<html lang>` and `dir` update.
- [ ] Language switcher is in the existing header/shell and shows native names.
- [ ] Remote language dictionaries are not included in the initial JS bundle.
- [ ] `lib/api.ts` sends `Content-Language` on every request.
- [ ] API `message` fields are shown as returned (not re-translated on the client).
- [ ] Tests/build pass.

---

# 41. Final report required from the Cursor Agent

After implementation, provide a concise report containing:

```text
1. Files created
2. Files modified
3. API endpoints added
4. Translation storage structure (UI dictionaries + API lang/*.json)
5. Locale middleware: Accept-Language → Content-Language → en fallback
6. Cache strategy
7. Client caching strategy
8. Which clients send Content-Language
9. Dependencies added/removed
10. Tests executed
11. Build results (including durpalla-web)
12. Any remaining issues
```

Also explicitly confirm:

```text
Initial Flutter bundle contains:
- English
- Bangla

Initial Flutter bundle does NOT contain:
- Hindi
- Arabic
- Chinese
- Urdu
- Farsi
- Turkish
- Spanish
- Italian
```

---

# Cursor Agent Execution Rule

**Implement the complete feature end-to-end. Do not stop after analysis or after creating a plan.**

If the repository contains an existing localization system, integrate with it.

If a reasonable implementation decision can be made from the existing codebase, make the decision and continue rather than asking for confirmation.

Only stop and ask for clarification if a required business decision genuinely cannot be determined from the repositories.

Before finishing, run the relevant tests/builds and fix implementation errors caused by this task.

Do not modify unrelated business logic.