# Booking load tests (open / closed loop)

k6 scripts that exercise the passenger **purchase** path against this API gateway:

`login → search → trip layout → cart lock → order confirm`

Goal: sustain **≥ 5 successful booking confirms per second** and compare open vs closed loop behaviour to spot bottlenecks (PHP workers, DB, seat locks, Cart/Booking services).

## Prerequisites

1. **Seed data** from the main Durpalla app (owns the DB schema):

```bash
cd ../durpalla
php artisan db:seed --class=TransportLoadTestSeeder

# Re-runable inventory reset (clears locks/booked on load-test trips):
LOADTEST_RESET=1 php artisan db:seed --class=TransportLoadTestSeeder
```

Seed creates:

| What | Detail |
|------|--------|
| Route | Dhaka → Khulna (LoadTest) |
| Bus | 40 seats/trip, 3 departures/day × 14 days (~1680 seats) |
| Launch cabins | 20 cabins/trip × 14 days |
| Ownership | `jolzan` (required for apigw customer booking) |
| Users | `01800000001` … `01800000050` / `LoadTest@123` |

2. API gateway running and pointed at the same DB.

3. [k6](https://grafana.com/docs/k6/latest/set-up/install-k6/) **or** Docker.

## Open loop vs closed loop

| Mode | k6 executor | Behaviour | Best for |
|------|-------------|-----------|----------|
| **Open loop** | `constant-arrival-rate` | New purchase starts every `1/TARGET_TPS` s even if previous ones are slow | Hitting a **TPS target**, finding queues/timeouts under arrival pressure |
| **Closed loop** | `constant-vus` | Each VU waits for full cycle before next booking | Capacity / concurrency (worker count, connection pools, seat races) |

Rule of thumb: open loop asks “can we absorb 5 bookings/s?”; closed loop asks “what happens with N concurrent buyers?”

## Run

```bash
# Open loop — force ~5 booking iterations/second for 2 minutes
./loadtests/run.sh open
# or
k6 run -e BASE_URL=http://127.0.0.1:8000 -e TARGET_TPS=5 -e DURATION=2m loadtests/open-loop.js

# Closed loop — fixed concurrent buyers
./loadtests/run.sh closed
# or
k6 run -e BASE_URL=http://127.0.0.1:8000 -e CLOSED_VUS=15 -e DURATION=2m loadtests/closed-loop.js
```

Docker default `BASE_URL` is `http://host.docker.internal:8000` (API on the host). Native k6 defaults to `http://127.0.0.1:8000`.

### Useful env vars

| Variable | Default | Meaning |
|----------|---------|---------|
| `BASE_URL` | `http://127.0.0.1:8000` | Gateway origin |
| `TARGET_TPS` | `5` | Open-loop arrival rate + success TPS threshold |
| `DURATION` | `2m` | Test length |
| `CLOSED_VUS` | `15` | Concurrent VUs (closed loop) |
| `TRIP_FROM` / `TRIP_TO` | Dhaka / Khulna | Search endpoints |
| `TRIP_DATE` | tomorrow | Override trip date (`YYYY-MM-DD`) |
| `VEHICLE_TYPE` | `bus` | Search filter |
| `LOADTEST_PASSWORD` | `LoadTest@123` | Seeded user password |

## How to read results

Custom metrics:

- `booking_tps` — successful confirms (rate = confirms / test seconds)
- `booking_confirm_success_rate` — fraction of purchase attempts that confirmed
- `booking_search_duration` / `booking_lock_duration` / `booking_confirm_duration` — stage latency

Bottleneck clues:

1. **Confirm p95 ≫ lock p95** → `BookingService` / DB transactions / jobs.
2. **Lock failures spike** → seat contention or inventory too small; re-seed with `LOADTEST_RESET=1`.
3. **Open loop maxVUs climbs, TPS falls** → app can’t keep arrival rate (workers, DB, network).
4. **Closed loop TPS ≈ VUs / cycle_ms** → scale VUs or cut latency to reach 5 TPS.

After a long run, reset inventory before the next test:

```bash
LOADTEST_RESET=1 php artisan db:seed --class=TransportLoadTestSeeder
```

## Flow under test

```http
POST /api/v1/auth/login
GET  /api/v1/search?trip_from=Dhaka&trip_to=Khulna&trip_date=...&type=bus
GET  /api/v1/trip/{id}?floor=1
POST /api/v1/cart/add          { "item_id": <schedule_cabin_mappings.id> }
POST /api/v1/order/confirm     { items: [...], payment_method: "cash", ... }
```

Purchase success is counted on a successful `order/confirm` (`success: true` + `order_id`). Payment gateway redirect is not included so the test stays focused on booking capacity.
