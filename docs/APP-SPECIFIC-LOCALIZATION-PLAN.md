# Durpalla APIGW — App-Specific Localization Resource Plan

Companion to [`SERVER-SIDE-LANGUAGE-FOR-EXTRA.md`](SERVER-SIDE-LANGUAGE-FOR-EXTRA.md).

This document defines **how all language files live on `durpalla-apigw`**, organized **per client app**, plus a separate **API message** layer for Laravel `__()` responses.

---

## 1. Goals

1. **One server source of truth** for remote locales (`hi`, `ar`, `zh`, `ur`, `fa`, `tr`, `es`, `it`).
2. **App-specific bundles** — each client downloads only its own strings.
3. **Keep `en` + `bn` bundled in clients**; server still stores them for parity checks and web refresh.
4. **No duplicate maintenance** — export scripts pull English keys from each repo; translators fill remote locales on the server.
5. **API response messages** stay separate from UI dictionaries.

---

## 2. Two localization surfaces (do not merge)

| Surface | Purpose | Consumed by | Storage on APIGW |
|--------|---------|-------------|------------------|
| **UI dictionaries** | Labels, nav, forms, empty states | Flutter / React clients | `resources/localizations/{app}/{locale}/` |
| **API messages** | JSON `message`, validation errors | Laravel at runtime | `lang/{locale}.json` + `lang/{locale}/validation.php` |

Clients display API `message` as returned. They do **not** re-translate those strings.

Locale resolution for API messages: `Accept-Language` → `Content-Language` → `en` (see main plan §11A).

---

## 3. Registered apps

Add to `config/localization.php`:

```php
'apps' => [
    'customer-app' => [
        'label' => 'Durpalla Customer (Flutter)',
        'repo' => 'durpalla-flutter-app',
        'format' => 'arb-flat',      // single flat JSON, ARB-compatible keys
        'bundled_locales' => ['en', 'bn'],
    ],
    'merchant-desk' => [
        'label' => 'Durpalla Merchant Desk (Flutter)',
        'repo' => 'durpalla-flutter-merchant-desk',
        'format' => 'arb-flat',
        'bundled_locales' => ['en', 'bn'],
    ],
    'web-merchant' => [
        'label' => 'Durpalla Merchant Web',
        'repo' => 'durpalla-web-merchant',
        'format' => 'i18next-namespaces', // multiple JSON files per locale
        'bundled_locales' => ['en', 'bn'],
    ],
    'web-customer' => [
        'label' => 'Durpalla Customer Web',
        'repo' => 'durpalla-web',
        'format' => 'i18next-namespaces',
        'bundled_locales' => ['en', 'bn'],
    ],
],
```

**App codes are stable API identifiers.** Clients pass their app code when downloading remote locales.

---

## 4. Directory layout on APIGW

```text
durpalla-apigw/
├── config/
│   └── localization.php          # locales, apps, version policy
├── lang/                         # API messages (Laravel only)
│   ├── en.json
│   ├── bn.json
│   ├── hi.json
│   ├── …                         # one JSON per locale
│   ├── en/
│   │   ├── validation.php
│   │   └── invoice.php           # existing
│   └── bn/
│       ├── validation.php
│       └── invoice.php
└── resources/
    └── localizations/
        ├── shared/               # optional cross-app keys (keep minimal)
        │   └── {locale}/
        │       └── common.json
        ├── customer-app/
        │   └── {locale}/
        │       ├── manifest.json # version, key_count, exported_at
        │       └── messages.json # flat map (ARB export)
        ├── merchant-desk/
        │   └── {locale}/
        │       ├── manifest.json
        │       └── messages.json
        ├── web-merchant/
        │   └── {locale}/
        │       ├── manifest.json
        │       ├── common.json
        │       ├── nav.json
        │       ├── auth.json
        │       └── …             # mirror src/i18n/locales/en/*.json
        └── web-customer/
            └── {locale}/
                ├── manifest.json
                ├── common.json
                └── nav.json
```

**Locales per app folder:** `en`, `bn`, `hi`, `ar`, `zh`, `ur`, `fa`, `tr`, `es`, `it`.

**Current state:** flat `resources/localizations/{locale}.json` (shared stub) → **migrate** to app folders in Phase 2.

---

## 5. Source-of-truth map (where English keys come from)

| App code | English source in client repo | Approx. size today |
|----------|--------------------------------|--------------------|
| `customer-app` | `lib/l10n/app_en.arb` | ~500+ keys |
| `merchant-desk` | `lib/l10n/app_en.arb` | ~800+ keys |
| `web-merchant` | `src/i18n/locales/en/*.json` (20 namespaces) | ~20 files |
| `web-customer` | `lib/i18n/messages.ts` → split into JSON namespaces | small today, will grow |
| `api` | grep `__('…')` / `trans('…')` in `app/Http`, `app/Requests` | ~310+ strings |

Bangla for bundled apps: export from `app_bn.arb` / `locales/bn/*.json` into the same server paths.

---

## 6. File formats

### 6.1 Flutter apps (`arb-flat`)

Export ARB → server `messages.json`:

```json
{
  "@@locale": "hi",
  "home": "होम",
  "search": "खोजें",
  "bookingFailed": "बुकिंग विफल"
}
```

Rules:

- Drop `@key` metadata blocks on export (or keep in separate `messages.meta.json` if needed for placeholders).
- Preserve `{placeholder}` names exactly (`{name}`, `{count}`, etc.).
- Keys = Flutter getter names (camelCase).

### 6.2 Web apps (`i18next-namespaces`)

Keep nested structure per namespace file — same shape as merchant repo:

```json
// web-merchant/hi/common.json
{
  "actions": { "save": "सहेजें", "cancel": "रद्द करें" },
  "language": { "label": "भाषा" }
}
```

Rules:

- Namespace = filename without `.json`.
- Client merges into i18n exactly as today (`common`, `nav`, `bookings`, …).
- Do not flatten web-merchant keys into dot notation on the server.

### 6.3 `manifest.json` (every app + locale folder)

```json
{
  "app": "customer-app",
  "locale": "hi",
  "version": 12,
  "fallback_locale": "en",
  "key_count": 523,
  "exported_at": "2026-09-05T10:00:00Z",
  "source_commit": "abc123",
  "checksum": "sha256:…"
}
```

Version bumps when English export changes or any translation changes.

### 6.4 API messages (`lang/{locale}.json`)

Laravel JSON translation — **English string as key**:

```json
{
  "Your item has been added to cart": "आइटem कार्ट में जोड़ा गया",
  "Hotel not found": "होटल नहीं मिला"
}
```

Separate from UI dictionaries. Maintained by scanning controllers / FormRequests.

---

## 7. API endpoints (app-aware)

Extend current routes:

```http
GET /api/v1/localizations
GET /api/v1/localizations/{app}
GET /api/v1/localizations/{app}/{locale}
GET /api/v1/localizations/{app}/{locale}/{namespace}   # web apps only
```

### 7.1 Index

```json
{
  "success": true,
  "data": {
    "default_locale": "en",
    "apps": [
      {
        "code": "customer-app",
        "bundled_locales": ["en", "bn"],
        "remote_locales": [
          { "code": "hi", "version": 12, "direction": "ltr" }
        ]
      }
    ]
  }
}
```

### 7.2 Flutter — full dictionary

```http
GET /api/v1/localizations/customer-app/hi
```

```json
{
  "success": true,
  "data": {
    "app": "customer-app",
    "locale": "hi",
    "version": 12,
    "fallback_locale": "en",
    "format": "arb-flat",
    "translations": { "home": "होम", "search": "खोजें" }
  }
}
```

### 7.3 Web — per namespace (lazy load)

```http
GET /api/v1/localizations/web-merchant/hi/common
GET /api/v1/localizations/web-merchant/hi/nav
```

Or one combined payload for simpler clients:

```http
GET /api/v1/localizations/web-merchant/hi?combined=1
```

Returns `{ "common": {…}, "nav": {…}, … }`.

### 7.4 Backward compatibility

Keep legacy route temporarily:

```http
GET /api/v1/localizations/{locale}
```

→ maps to `web-customer` or returns 410 with migration notice. Remove after all clients send `{app}`.

---

## 8. Shared vs app-specific keys

| Keep shared (`shared/{locale}/common.json`) | Keep app-specific |
|---------------------------------------------|-------------------|
| `Cancel`, `Save`, `Loading…` if identical UX | `booking.confirm`, merchant `settlements.*` |
| Language native names | App titles, domain terms (PNR, Launch, Merchant) |
| Generic errors used identically | Flutter-only vs web-only screens |

**Rule:** default to **app-specific**. Move to `shared/` only when the **same English string** must stay identical in **3+ apps** (avoid premature DRY).

---

## 9. Export / sync pipeline

Add scripts under `durpalla-apigw/scripts/i18n/` (or each repo exports, CI uploads):

| Script | Input | Output |
|--------|-------|--------|
| `export-flutter-arb.php` | `app_en.arb`, `app_bn.arb` | `customer-app/{locale}/messages.json` |
| `export-web-merchant.php` | `src/i18n/locales/{lng}/*.json` | `web-merchant/{locale}/*.json` |
| `export-web-customer.php` | `lib/i18n/**/*.json` | `web-customer/{locale}/*.json` |
| `export-api-messages.php` | static scan of `__('…')` | `lang/{locale}.json` keys list + missing report |
| `validate-localizations.php` | all resources | CI fail on missing keys, broken placeholders, unknown locales |

**Recommended flow:**

```text
1. Developer changes English in client repo (ARB / JSON)
2. CI in client repo runs i18n audit (existing merchant script pattern)
3. Release job exports en (+ bn) to apigw paths, bumps manifest.version
4. Translators fill hi/ar/… in apigw only (or via PR to apigw resources)
5. validate-localizations.php runs in apigw CI
6. Clients download updated remote locales by version
```

Do **not** hand-edit remote locales in client repos for `hi+` — server owns remote translations.

---

## 10. Translation workflow for remote locales

For each app + locale:

1. Copy English export as template (`messages.json` / namespace files).
2. Translate UI strings; keep placeholders and HTML out of values.
3. Run placeholder parity check (`{name}` in en must exist in hi).
4. Native speaker review for Durpalla terms (Launch, PNR, Booking, Merchant, etc.).
5. Mark `manifest.json` version complete.

**Priority order:**

1. `api` — `lang/*.json` (user-visible errors everywhere)
2. `customer-app` — highest traffic
3. `web-customer`
4. `merchant-desk`
5. `web-merchant`

---

## 11. Service layer changes (APIGW)

Update `LocalizationService`:

```text
dictionary(app, locale, ?namespace)
metadata(?app)
versionFor(app, locale)
pathFor(app, locale, ?namespace)
```

Cache keys: `localization:{app}:{locale}:{namespace}`.

**Do not** load all apps into one response. **Do not** query DB per request — file + cache only.

---

## 12. Client download contract (reminder)

| Client | App code | Download |
|--------|----------|----------|
| `durpalla-flutter-app` | `customer-app` | Full `messages.json` on first select of remote locale |
| `durpalla-flutter-merchant-desk` | `merchant-desk` | Same |
| `durpalla-web-merchant` | `web-merchant` | Per namespace or combined |
| `durpalla-web` | `web-customer` | Per namespace or combined |

All send `Accept-Language` + `Content-Language` on API calls after language change.

---

## 13. Implementation phases

### Phase A — Structure & config (1–2 days)

- [ ] Add `apps` registry to `config/localization.php`
- [ ] Create folder tree under `resources/localizations/{app}/{locale}/`
- [ ] Add `manifest.json` schema
- [ ] Move current stub `resources/localizations/en.json` → `web-customer/en/common.json` (or delete after export)

### Phase B — English + Bangla export (2–3 days)

- [ ] Export `customer-app` en/bn from ARB
- [ ] Export `merchant-desk` en/bn from ARB
- [ ] Copy `web-merchant` en/bn namespace files
- [ ] Expand `web-customer` en/bn namespace files from `lib/i18n/messages.ts`
- [ ] Generate API message key inventory; complete `lang/en.json`, expand `lang/bn.json`

### Phase C — API v2 routes (1–2 days)

- [ ] `GET /localizations/{app}/{locale}` (+ optional `{namespace}`)
- [ ] Update `LocalizationService` for app paths
- [ ] Feature tests per app + locale + 404 on bad app/locale
- [ ] Deprecation notice on legacy `GET /localizations/{locale}`

### Phase D — Remote locale translation (ongoing)

- [ ] Fill `hi`, `ar`, `zh`, `ur`, `fa`, `tr`, `es`, `it` per app (start with api + customer-app)
- [ ] Replace English stubs in `ar.json`, `zh.json`, etc.

### Phase E — Client wiring (1–2 days)

- [ ] Flutter: pass `customer-app` / `merchant-desk` in download URL
- [ ] Web: pass app code; web-merchant load namespaces lazily
- [ ] Version check: skip download when manifest version unchanged

### Phase F — CI & validation (1 day)

- [ ] `validate-localizations.php` in apigw CI
- [ ] Export scripts callable from client repos (document in each README)
- [ ] Monthly audit: missing keys vs English

---

## 14. Validation rules (CI)

Fail build if:

- Unknown locale or app code in path
- Remote locale missing keys present in English for same app
- Placeholder mismatch between en and translation
- Empty translation value
- Invalid JSON / UTF-8
- `manifest.version` not incremented when English changed
- API `lang/{locale}.json` missing keys still returned in English from controllers

Warn (non-blocking):

- Unused keys in server files (orphans)
- Translation longer than 200% of English length (UI overflow review)

---

## 15. Acceptance criteria

- [ ] All **4 client apps** have dedicated folders with **en + bn** complete exports.
- [ ] All **8 remote locales** exist for each app (may be partial at first; English fallback documented).
- [ ] API messages in `lang/*.json` cover all user-facing `__()` strings.
- [ ] Endpoints require `app` and return correct format per app.
- [ ] Clients download only their app’s bundle.
- [ ] No breaking change to booking/payment/auth APIs.
- [ ] Tests: metadata, each app locale 200, invalid app 404, ETag, Accept-Language on messages.

---

## 16. Current inventory snapshot (Sep 2026)

| Resource | Status |
|----------|--------|
| `resources/localizations/{app}/{locale}/` | **Done** — 4 apps × 10 locales |
| `customer-app` / `merchant-desk` | **Done** — exported from ARB (`messages.json`) |
| `web-merchant` | **Done** — 20 namespaces × 10 locales |
| `web-customer` | **Done** — `common.json` × 10 locales |
| `lang/en.json` + remote `lang/*.json` | **Done** — 227 API message keys (remote copies English until translated) |
| `scripts/i18n/export-all.php` | **Done** |
| `scripts/i18n/export-api-messages.php` | **Done** |
| `scripts/i18n/validate-localizations.php` | **Done** |
| Native translations for `hi+` remote locales | **Pending** — structure in place, values mirror English |

---

## 17. Cursor Agent execution order

When implementing this plan:

1. Read this doc + `SERVER-SIDE-LANGUAGE-FOR-EXTRA.md`.
2. Phase A → C on apigw first (structure + API).
3. Phase B exports (can run in parallel per repo).
4. Phase E client URL updates.
5. Phase D translations incrementally (do not block release on 100% hi/ar/…).

**Do not** wipe controllers or routes. **Do not** put API secrets in translation files.

---

## 18. Related files (already implemented)

| File | Role |
|------|------|
| `app/Support/ApiLocale.php` | Accept-Language → Content-Language → en |
| `app/Http/Middleware/SetApiLocale.php` | Sets locale on all API requests |
| `app/Services/LocalizationService.php` | File load + cache (extend for `{app}`) |
| `app/Http/Controllers/Api/v1/LocalizationController.php` | Public download API |
| `tests/Feature/LocalizationApiTest.php` | Baseline tests |

Extend these; do not duplicate locale resolution logic.
