# Extra guests — Agent & Customer web (React)

How agent and customer React web handle guests above a room’s capacity. Merchants configure capacity (and child policies) separately — see `EXTRA_GUEST_MERCHANT_WEB_AND_APP.md`.

| Surface | Repo | Stack |
|---------|------|-------|
| Customer web | `durpalla-web` | React (Next.js) |
| Agent API | `durpalla-apigw` (`AgentHotelBookingService`) | Laravel |
| Agent app | `durpalla-agent` | Android (consumes agent hotel APIs) |
| Customer mobile (reference) | `durpalla-flutter-app` | Flutter — similar UX rules |

---

## Source of capacity

Bookable rooms expose:

```json
"max_occupancy": 2
```

from `GET …/hotel/{id}/rooms` (customer) or `GET …/agent/hotels/{id}/rooms` (agent).

That value comes from merchant room setup (`hotel_rooms.max_occupancy` → synced `hotel_room_types`).

**Pricing note:** Customer and agent **quote / hold** use nightly room rate + service charge + VAT (`HotelPricingService::quoteStay`). They do **not** apply merchant child-policy fees. Extra guests are handled by **more rooms** (or UX allowances), not by inventing an extra-guest surcharge on these channels.

---

## Customer web (`durpalla-web`)

### Behaviour

1. Cart tracks `adults`, `children`, and each line’s `maxOccupancy`.
2. Total bed capacity = `Σ(quantity × maxOccupancy)`.
3. Helpers (`lib/hotel-cart.ts`):
   - `roomsNeededForGuests(guests, maxOccupancy)` → `ceil(guests / occupancy)`
   - `hotelCartGuestCapacity(cart)` → total sleep capacity of selected rooms
4. When adding a room (`HotelDetails.tsx` → `openAddRoom`):
   - Suggested quantity covers guest shortfall vs current cart capacity.
5. If quantity is reduced and capacity falls below the party size:
   - `HotelCapacityShortfallDialog` opens (“Not enough room capacity”).
   - Actions: **Keep as is** or **Add another room**.

UI: `app/hotels/[id]/components/HotelCartChrome.tsx` (`HotelCapacityShortfallDialog`)  
Cart logic: `lib/hotel-cart.ts`  
Details wiring: `app/hotels/[id]/components/HotelDetails.tsx`

### User-facing rule (web)

| Situation | What the UI does |
|-----------|------------------|
| Guests fit in selected rooms | Normal add / checkout |
| Guests exceed cart capacity | Prompt to **add another room** for the shortfall |
| User dismisses prompt (“Keep as is”) | Cart stays under-capacity until they add rooms or reduce guests |

There is **no** “pay extra guest fee” action on customer web today (API has no customer extra-guest fee on quote/hold).

### Suggested product copy (if you extend web)

Align with customer mobile:

- Prefer **Add another room** when overflow > 0.
- Optional later: **Stay with +1 extra guest** only when overflow === 1, without inventing a fee until merchant/API support exists.

---

## Customer mobile (Flutter) — reference parity

Customer app (`durpalla-flutter-app`) already gates add-to-cart on `max_occupancy`:

| Overflow vs `occupancy × qty` | Dialog options |
|-------------------------------|----------------|
| Exactly **+1** | Cancel · Stay with +1 extra guest · Add another room |
| **More than +1** | Cancel · Add another room |

“Add another room” sets quantity to `ceil(guests / occupancy)` if inventory / 4-room cart cap allows.  
“Stay with +1 extra guest” adds at current quantity **without** changing quote math (no surcharge field).

Use this as the target UX if customer web should match the app.

---

## Agent (`durpalla-apigw` + `durpalla-agent`)

### Rooms listing (multi-room aware — aligned with customer)

`AgentHotelBookingService` rooms-for-stay:

- Keeps room types visible when `adults + children > max_occupancy`.
- Returns `rooms_needed = ceil(guests / max_occupancy)`, plus `available_count` / `available_rooms`.
- Checks inventory for `rooms_needed` units (not just 1).
- Empty list message when party cannot fit within 4 × largest occupancy:  
  `No room type here fits {N} guests (largest max occupancy is {M}).`
- Otherwise empty means no types / no inventory for dates.

| Party size | Room `max_occupancy` | Result |
|------------|----------------------|--------|
| ≤ occupancy | Fits in one | Room shown; `rooms_needed: 1` |
| > occupancy | Needs multiple | Room **shown**; `rooms_needed: ceil(guests/occ)` |

Hold validates bed capacity: `Σ(quantity × max_occupancy) >= adults + children`.

### Hold / confirm

- Agent hold: `POST /api/v1/agent/hotels/hold` (active agent).
- Confirm: `POST /api/v1/agent/hotels/confirm`.
- Pricing remains `quoteStay` (no child-policy surcharge on this path).

### Agent app implications

When building or updating agent hotel booking UI:

1. Show `max_occupancy` on each room card.
2. Prefer API `rooms_needed` for default quantity (fallback: `ceil(guests / max_occupancy)`).
3. If the list is empty, show the API empty-state message (includes largest occupancy when the party cannot fit within 4 rooms).
4. Before hold, send `lines: [{ room_type_id, quantity }]` so capacity covers the party.
5. Do not invent an extra-guest fee in the agent app unless the API adds an explicit field and merchant config.

---

## Comparison matrix

| Concern | Customer web | Customer app | Agent rooms API |
|---------|--------------|--------------|-----------------|
| Capacity field | `max_occupancy` | `max_occupancy` | `max_occupancy` |
| Over capacity | Dialog → add room / +1 soft | Dialog → add room / optional +1 | Room kept; `rooms_needed` |
| Auto qty for party | Yes (`roomsNeededForGuests`) | Yes on “Add another room” | Yes (`rooms_needed`) |
| Extra guest fee on quote | No | No | No |
| Child policy fees | No | No | No |

---

## Implementation checklist (future work)

### Customer web

- [x] Optionally offer **Stay with +1 extra guest** when shortfall === 1 (match Flutter).
- [x] Soft-block checkout when shortfall > 1 (+1 soft allow).
- [ ] If product adds paid extra guests: read fee from API only — never hardcode.

### Agent

- [x] Agent UI: multi-room quantity helper when party > single-room occupancy.
- [x] Align agent rooms API with customer (`rooms_needed` + keep room visible).
- [x] Surface largest `max_occupancy` in empty-state UI copy.

### Shared API (only if product requires paid extras)

- [ ] Merchant-configurable adult extra-guest fee (separate from child policies).
- [ ] Include fee lines in `quoteStay` / hold quote JSON.
- [ ] Wire customer web, customer app, and agent to display and charge that line.

Until then: **extra guests ⇒ more rooms (or +1 soft allow on customer app), not a silent surcharge.**

---

## Related files

| Area | Path |
|------|------|
| Customer cart helpers | `durpalla-web/lib/hotel-cart.ts` |
| Customer details / prompts | `durpalla-web/app/hotels/[id]/components/HotelDetails.tsx` |
| Capacity dialog | `durpalla-web/app/hotels/[id]/components/HotelCartChrome.tsx` |
| Agent rooms filter | `durpalla-apigw/app/Services/AgentHotelBookingService.php` |
| Customer rooms (multi-room aware) | `durpalla-apigw/app/Services/Hotel/HotelBookingService.php` (`rooms_needed`) |
| Quote (no extra-guest fee) | `durpalla-apigw/app/Services/Hotel/HotelPricingService.php` |
| Flutter occupancy dialog | `durpalla-flutter-app/lib/screens/hotel_details_screen.dart` |
| Merchant config doc | `docs/EXTRA_GUEST_MERCHANT_WEB_AND_APP.md` |
