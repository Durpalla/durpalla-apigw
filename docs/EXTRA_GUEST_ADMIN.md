# Extra guest / extra bed — Admin panel (`durpalla`)

Guide for configuring room capacity and child / extra-bed policies in the Durpalla **admin** Hotel module.

| Surface | Repo | Role |
|---------|------|------|
| Admin panel | `durpalla` (Laravel + Hotel module) | Full CRUD: room occupancy + child / extra-bed policies |
| Merchant web / app | see `EXTRA_GUEST_MERCHANT_WEB_AND_APP.md` | Merchants can also configure the same data |
| Agent / customer web | see `EXTRA_GUEST_AGENT_AND_CUSTOMER_WEB.md` | Consume `max_occupancy`; do **not** bill child-policy fees yet |
| API / engine | `durpalla-apigw` (+ shared models in `durpalla`) | `ChildRuleEngine` applies policies on desk / admin bookings |

Related docs:

- `EXTRA_GUEST_MERCHANT_WEB_AND_APP.md`
- `EXTRA_GUEST_AGENT_AND_CUSTOMER_WEB.md`

---

## What “extra guest” means in admin

Admins configure two related things per hotel:

1. **Room capacity** — how many people a room sleeps (`max_occupancy`, plus `max_adults` / `max_children`).
2. **Child / extra-bed policies** — age bands, bed type (including **extra bed**), and how children are priced when a booking includes `children_ages`.

Customer / agent online quote today uses **nightly room rate + service charge + VAT**. Child-policy fees are applied on **admin hotel booking** and **merchant desk booking** paths via `ChildRuleEngine`.

---

## 1. Room occupancy (required for all channels)

Capacity is stored on `hotel_rooms` and synced into bookable `hotel_room_types.max_occupancy` for customer / agent APIs.

### Fields

| Field | Form / DB | Range | Meaning |
|-------|-----------|-------|---------|
| Max adults | `max_adults` | 1–20 (admin form) | Suggested adult cap |
| Max children | `max_children` | 0–10 (admin form) | Suggested child cap |
| Max occupancy | `max_occupancy` | 1–20 | **Total guests** the room sleeps |

**Tip:** Keep `max_occupancy >= max_adults` (and typically enough headroom for expected children). Customer and agent apps use `max_occupancy` to decide whether guests fit or need another room.

### Admin UI steps

1. Log in to admin → **Hotel** → open a hotel (`/dashboard/hotel/{id}`).
2. Open the **Rooms** tab (or go to `/dashboard/hotel/{hotel}/rooms`).
3. **Add** or **Edit** a room.
4. Set:
   - Max adults
   - Max children
   - Max occupancy (total guests allowed)
5. Save.

UI: `Modules/Hotel/Resources/views/hotel-rooms/_form.blade.php`  
Controller: `Modules/Hotel/Http/Controllers/HotelRoomController.php`  
Routes: `dashboard.hotel.rooms.*` under `Modules/Hotel/Routes/web.php`

---

## 2. Child / extra-bed policies

Policies live in `hotel_child_policies`, scoped to a hotel (optional rate plan override).

The admin modal title is **“Add/Edit Extra Bed / Child Policy”**.

### Fields

| Field | Form name | Allowed values | Meaning |
|-------|-----------|----------------|---------|
| Rate plan | `rate_plan_id` | nullable | Plan-specific override; empty = **all / hotel default** |
| Min age | `min_age` | 0–17 | Inclusive lower age |
| Max age | `max_age` | 0–17 (≥ min) | Inclusive upper age |
| Bed type | `bed_type` | `no_bed`, `extra_bed`, `adult_bed` | How the child is accommodated |
| Price type | `price_type` | `free`, `fixed`, `percentage`, `adult` | How the child is charged |
| Price value | `price_value` | ≥ 0 | Required for `fixed` / `percentage` (BDT or %) |

### Pricing rules (`ChildRuleEngine`)

For each child age on an admin / desk booking:

| `price_type` | Charge |
|--------------|--------|
| `free` | 0 |
| `fixed` | `price_value × nights` |
| `percentage` | `(adult_unit_price × price_value / 100) × nights` |
| `adult` | `adult_unit_price × nights` |

If no matching age policy exists, the engine charges **full adult price × nights**.

`bed_type = extra_bed` is validated for availability on booking (engine currently treats extra bed as available unless inventory rules reject it).

Resolution order: **rate-plan-specific** policy first, then hotel-wide (`rate_plan_id` null).

### Admin UI steps

1. Open hotel detail → **Child Policies** tab.
2. Click **Add Policy**.
3. In the modal, set:
   - Optional rate plan (or leave “All rate plans”)
   - Min / max age
   - Bed type — use **Extra bed** when the child needs an add-on bed
   - Price type + price value (when fixed / percentage)
4. **Save Policy**.
5. Edit / delete existing rows from the same table.

Standalone list (same hotel): `/dashboard/hotel/{hotel}/child-policies`

UI: `Modules/Hotel/Resources/views/child-policies/_form.blade.php`  
Hotel tab: `Modules/Hotel/Resources/views/hotels/show.blade.php` (`#tab-child-policies`)  
Controller: `Modules/Hotel/Http/Controllers/HotelChildPolicyController.php`  
Validation: `HotelChildPolicyStoreRequest` / `HotelChildPolicyUpdateRequest`

### Example policies

| Ages | Bed type | Price type | Price value | Intent |
|------|----------|------------|-------------|--------|
| 0–5 | No bed | Free | — | Infants / toddlers, no extra bed |
| 6–11 | Extra bed | Fixed | 500 | Child on extra bed, flat fee / night |
| 12–17 | Adult bed | Percentage | 75 | Near-adult, 75% of adult rate |

---

## 3. Where policies are applied

| Booking path | Uses `max_occupancy` | Uses child / extra-bed policies for price |
|--------------|----------------------|-------------------------------------------|
| Admin hotel booking (`HotelBookingService` + `ChildRuleEngine`) | Capacity on room product | **Yes** — needs `rooms.*.children_ages` |
| Merchant desk booking | Yes | **Yes** — same engine rules (via API) |
| Customer app / customer web hold | Yes (cart / rooms UX) | **No** — quote is nightly room rate only |
| Agent hotel rooms / hold | Yes (fit filter) | **No** — same as customer pricing |

When creating an admin booking with children, send `children_ages` (array of ages, one per child) so the engine can match policies.

Request rules: `Modules/Hotel/Http/Requests/HotelBookingCreateRequest.php`  
Engine: `Modules/Hotel/Services/ChildRuleEngine.php`  
Apply: `Modules/Hotel/Services/HotelBookingService.php`

---

## 4. Recommended admin setup

1. Set **Max occupancy** accurately on every room (drives customer / agent “need another room” behaviour).
2. On each hotel, add child policies covering ages **0–17** without gaps (or accept adult fallback pricing).
3. Prefer:
   - Young children: `no_bed` + `free` or low `fixed`
   - Older children needing a bed: `extra_bed` + `fixed` or `percentage`
   - Near-adult teens: `adult_bed` + `adult` or high `percentage`
4. Use rate-plan-specific overrides only when a plan must differ from the hotel default; re-check after peak-season rate plan changes.
5. Merchants can manage the same data from merchant web / desk — admin remains the source for ops / support overrides.

---

## 5. Related code (quick map)

| Area | Path |
|------|------|
| Hotel show + Child Policies tab | `Modules/Hotel/Resources/views/hotels/show.blade.php` |
| Room form (occupancy) | `Modules/Hotel/Resources/views/hotel-rooms/_form.blade.php` |
| Policy form (extra bed) | `Modules/Hotel/Resources/views/child-policies/_form.blade.php` |
| Room CRUD | `Modules/Hotel/Http/Controllers/HotelRoomController.php` |
| Policy CRUD | `Modules/Hotel/Http/Controllers/HotelChildPolicyController.php` |
| Web routes | `Modules/Hotel/Routes/web.php` (`dashboard/hotel/{hotel}/rooms`, `…/child-policies`) |
| Entity | `Modules/Hotel/Entities/HotelChildPolicy.php` |
| Pricing engine | `Modules/Hotel/Services/ChildRuleEngine.php` |
| Admin booking apply | `Modules/Hotel/Services/HotelBookingService.php` |
| Merchant API (shared) | `app/Http/Controllers/Api/v1/Merchant/MerchantHotelChildPolicyController.php` |

---

## 6. Gaps / follow-ups

- Customer / agent online checkout does **not** yet bill `HotelChildPolicy` amounts — only room nightly quote (see agent/customer doc).
- There is no separate admin field named `extra_guest_fee` for adults over occupancy; adult overflow is handled by adding rooms (or channel UX allowances), not by inventing a surcharge.
- Admin booking create UI should pass `children_ages` whenever children &gt; 0; without ages, child policies are skipped and pricing falls back to room rate only (or adult rate when ages are missing but children count is set without ages — engine requires the ages array).
