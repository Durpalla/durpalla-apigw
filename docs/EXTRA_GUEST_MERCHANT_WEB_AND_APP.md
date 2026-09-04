# Extra guest configuration — Merchant web & Merchant app

Guide for configuring room capacity and child / extra-guest policies on Durpalla merchant surfaces.

| Surface | Repo | Role |
|---------|------|------|
| Merchant web | `durpalla-web-merchant` (React) | Room occupancy + view child policies |
| Merchant desk app | `durpalla-flutter-merchant-desk` (Flutter) | Room occupancy + create / edit / delete child policies |
| Admin panel | `durpalla` | Full CRUD — see `EXTRA_GUEST_ADMIN.md` |
| API | `durpalla-apigw` | Source of truth |

---

## What “extra guest” means for merchants

Merchants configure two related things:

1. **Room capacity** — how many people a room sleeps (`max_occupancy`, plus optional `max_adults` / `max_children`).
2. **Child policies** — age bands, bed type (including extra bed), and how children are priced on **merchant desk bookings**.

Customer online quote / hold pricing today uses **nightly room rate + service charge + VAT**. It does **not** yet apply merchant child-policy fees. Child policies are applied on the **merchant desk booking** path (`ChildRuleEngine`).

---

## 1. Room occupancy (required for all channels)

Capacity is stored on `hotel_rooms` and synced into bookable `hotel_room_types.max_occupancy` for customer / agent APIs.

### Fields

| Field | API key | Range | Meaning |
|-------|---------|-------|---------|
| Max adults | `max_adults` | 0–20 | Suggested adult cap |
| Max children | `max_children` | 0–20 | Suggested child cap |
| Max occupancy | `max_occupancy` | 1–20 | **Total guests** the room sleeps |

If `max_occupancy` is omitted on create, the API defaults it to `max(1, max_adults)` (or `2` when adults are also omitted).

### Merchant web

1. Open the hotel → **Rooms**.
2. Create or edit a room.
3. In **Occupancy**, set:
   - Max adults
   - Max children
   - Max occupancy (total guests allowed)
4. Save.

UI: `src/features/hotels/hotel-room-form.tsx`  
Payload: `src/features/hotels/hotel-room-form-helpers.ts` → `max_occupancy`, `max_adults`, `max_children`

### Merchant app (Flutter desk)

1. Open hotel detail → room create / edit.
2. Set the same occupancy fields (`max_occupancy`, etc.).
3. Save via `MerchantHotelService` create/update room.

### API

```http
POST   /api/v1/merchant/hotels/{hotelId}/rooms
PATCH  /api/v1/merchant/hotels/{hotelId}/rooms/{roomId}
```

Controller: `MerchantHotelRoomController`

**Tip:** Keep `max_occupancy >= max_adults` (and typically `>= max_adults + expected children`). Customer and agent apps use `max_occupancy` to decide whether guests fit or need another room.

---

## 2. Child / extra-bed policies

Policies live in `hotel_child_policies` and are scoped to a hotel (optional rate plan).

### Fields

| Field | API key | Allowed values | Meaning |
|-------|---------|----------------|---------|
| Min age | `min_age` | 0–17 | Inclusive lower age |
| Max age | `max_age` | 0–17 | Inclusive upper age |
| Bed type | `bed_type` | `no_bed`, `extra_bed`, `adult_bed` | How the child is accommodated |
| Price type | `price_type` | `free`, `fixed`, `percentage`, `adult` | How the child is charged |
| Price value | `price_value` | ≥ 0 | Fixed for `fixed` / `percentage` |
| Rate plan | `rate_plan_id` | nullable | Plan-specific override; `null` = hotel default |

### Pricing rules (`ChildRuleEngine`)

For each child age on a desk booking:

| `price_type` | Charge |
|--------------|--------|
| `free` | 0 |
| `fixed` | `price_value × nights` |
| `percentage` | `(adult_unit_price × price_value / 100) × nights` |
| `adult` | `adult_unit_price × nights` |

If no matching age policy exists, the engine charges **full adult price × nights**.

`bed_type = extra_bed` is validated for availability on desk booking (engine currently treats extra bed as available).

Resolution order: rate-plan-specific policy first, then hotel-wide policy (`rate_plan_id` null).

### Merchant app — configure here

1. Open hotel detail.
2. Scroll to **Child policies**.
3. Tap **Policy** to add, or edit / delete an existing row.
4. Set ages, bed type, price type, optional rate plan id, and price value.
5. Save.

UI: `lib/screens/hotels/hotel_detail_screen.dart` (`_showChildPolicyDialog`)  
Service: `lib/services/merchant_hotel_service.dart` (`createChildPolicy` / `updateChildPolicy` / `deleteChildPolicy`)

### Merchant web — view today

1. Open hotel → **Policies** tab.
2. Child policies are listed (ages, bed type, price type / value).

API client methods already exist (`hotelApi.childPolicies`, `createChildPolicy`, …) in `src/api/services.ts`. The Policies tab is primarily **read-only** today; full create/edit UI is available on the merchant app. Use the app (or API) to mutate policies until web CRUD is added.

### API

```http
GET    /api/v1/merchant/hotels/{hotelId}/child-policies
POST   /api/v1/merchant/hotels/{hotelId}/child-policies
PATCH  /api/v1/merchant/hotels/{hotelId}/child-policies/{policyId}
DELETE /api/v1/merchant/hotels/{hotelId}/child-policies/{policyId}
```

Controller: `MerchantHotelChildPolicyController`

Example create body:

```json
{
  "min_age": 0,
  "max_age": 5,
  "bed_type": "no_bed",
  "price_type": "free",
  "price_value": null,
  "rate_plan_id": null
}
```

```json
{
  "min_age": 6,
  "max_age": 11,
  "bed_type": "extra_bed",
  "price_type": "fixed",
  "price_value": 500,
  "rate_plan_id": null
}
```

```json
{
  "min_age": 12,
  "max_age": 17,
  "bed_type": "adult_bed",
  "price_type": "percentage",
  "price_value": 75
}
```

---

## 3. Where policies are applied

| Booking path | Uses `max_occupancy` | Uses child policies for price |
|--------------|----------------------|-------------------------------|
| Merchant desk booking | Capacity on room product | **Yes** — `MerchantDeskBookingService` + `ChildRuleEngine` (needs `children_ages`) |
| Customer app / customer web hold | Yes (cart / rooms UX) | **No** — quote is nightly room rate only |
| Agent hotel rooms / hold | Yes (agent rooms filter by single-room fit) | **No** — same pricing service as customer |

When creating a desk booking with children, send `children_ages` (array of ages) so the engine can match policies.

---

## 4. Recommended merchant setup

1. Set **Max occupancy** accurately on every room (this drives customer / agent “need another room” behaviour).
2. Add child policies covering ages **0–17** without gaps (or accept adult fallback pricing on desk).
3. Prefer:
   - Young children: `no_bed` + `free` or low `fixed`
   - Older children needing a bed: `extra_bed` + `fixed` or `percentage`
   - Near-adult teens: `adult_bed` + `adult` or high `percentage`
4. Re-check Policies after peak-season rate plan changes if you use plan-specific overrides.

---

## 5. Related code (quick map)

| Area | Path |
|------|------|
| Room CRUD API | `app/Http/Controllers/Api/v1/Merchant/MerchantHotelRoomController.php` |
| Child policy API | `app/Http/Controllers/Api/v1/Merchant/MerchantHotelChildPolicyController.php` |
| Pricing engine | `app/Services/Hotel/ChildRuleEngine.php` |
| Desk booking apply | `app/Services/Hotel/MerchantDeskBookingService.php` |
| Model | `app/Models/HotelChildPolicy.php` |
| Routes | `routes/api/v1/merchant.php` (`hotels/{hotelId}/child-policies`) |

---

## 6. Gaps / follow-ups

- Merchant **web** child-policy **create/edit** UI is not fully wired; use merchant app or API.
- Customer / agent online checkout does **not** yet bill `HotelChildPolicy` amounts — only room nightly quote.
- There is no separate merchant field named `extra_guest_fee` for adults over occupancy; adult overflow is handled on booking UIs by adding rooms (and on customer app optionally allowing +1 over capacity without an invented surcharge).
