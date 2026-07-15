import http from 'k6/http';
import { check, fail } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';
import { cfg, mobileForVu } from './config.js';

export const searchDuration = new Trend('booking_search_duration', true);
export const tripDuration = new Trend('booking_trip_layout_duration', true);
export const lockDuration = new Trend('booking_lock_duration', true);
export const confirmDuration = new Trend('booking_confirm_duration', true);
export const bookingOk = new Counter('booking_confirm_ok');
export const bookingFail = new Counter('booking_confirm_fail');
export const bookingSuccessRate = new Rate('booking_confirm_success_rate');
/** Successful purchase/confirm completions per second (custom rate via Counter / time). */
export const bookingTps = new Counter('booking_tps');

const jsonHeaders = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

function url(path, config) {
  return `${config.baseUrl}${config.apiPrefix}${path}`;
}

function parseJson(res) {
  try {
    return res.json();
  } catch (_) {
    return null;
  }
}

/**
 * Flatten trip layout seats/cabins into available item_ids.
 */
export function collectAvailableItems(layout) {
  const items = [];
  if (!layout || typeof layout !== 'object') {
    return items;
  }

  const pushIfAvailable = (row) => {
    if (!row || typeof row !== 'object') return;
    const status = row.status;
    const itemId = row.item_id;
    const type = row.cabin_type || row.type || 'seat';
    if (itemId && (status === 1 || status === true)) {
      items.push({
        item_id: itemId,
        type,
        trip_id: row.trip_id,
        cabin_no: row.cabin_no,
        fare: row.fare,
        boardingPoint: row.boardingPoint || null,
      });
    }
  };

  const walk = (node) => {
    if (!node) return;
    if (Array.isArray(node)) {
      node.forEach(walk);
      return;
    }
    if (typeof node !== 'object') return;
    if (node.item_id !== undefined) {
      pushIfAvailable(node);
      return;
    }
    Object.values(node).forEach(walk);
  };

  // Common keys from formatTriplayout
  walk(layout.seats);
  walk(layout.cabins);
  walk(layout.seats_layout);
  walk(layout.cabins_layout);
  walk(layout);

  // de-dupe by item_id
  const seen = new Set();
  return items.filter((it) => {
    if (seen.has(it.item_id)) return false;
    seen.add(it.item_id);
    return true;
  });
}

export function login(config, mobile) {
  const res = http.post(
    url('/auth/login', config),
    JSON.stringify({
      mobile,
      password: config.password,
      device_id: `k6-loadtest-${mobile}`,
    }),
    { headers: jsonHeaders, tags: { name: 'auth_login' } },
  );

  const body = parseJson(res);
  const ok = check(res, {
    'login status 200': (r) => r.status === 200,
    'login success': () => body && body.success === true && !!body.token,
  });

  if (!ok || !body?.token) {
    return null;
  }

  return {
    token: body.token,
    user: body.user || { mobile, name: `Load ${mobile}` },
  };
}

export function searchTrips(config, authHeaders) {
  const date = config.tripDate || new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  const qs = Object.entries({
    trip_from: config.tripFrom,
    trip_to: config.tripTo,
    trip_date: date,
    type: config.vehicleType,
  })
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
    .join('&');

  const res = http.get(url(`/search?${qs}`, config), {
    headers: { ...authHeaders, Accept: 'application/json' },
    tags: { name: 'trip_search' },
  });
  searchDuration.add(res.timings.duration);

  const body = parseJson(res);
  check(res, {
    'search status 200': (r) => r.status === 200,
    'search has data': () => body && Array.isArray(body.data),
  });

  return Array.isArray(body?.data) ? body.data : [];
}

export function fetchTripLayout(config, tripId, authHeaders) {
  const res = http.get(url(`/trip/${tripId}?floor=1`, config), {
    headers: { ...authHeaders, Accept: 'application/json' },
    tags: { name: 'trip_layout' },
  });
  tripDuration.add(res.timings.duration);

  const body = parseJson(res);
  check(res, {
    'trip layout 200': (r) => r.status === 200,
  });

  return body?.data || null;
}

/**
 * Build inventory of available seat/cabin mappings across upcoming load-test trips.
 */
export function buildInventory(config, token) {
  const authHeaders = token ? { Authorization: `Bearer ${token}` } : {};
  const trips = searchTrips(config, authHeaders);
  const inventory = [];

  // Prefer bus trips matching load-test vehicle naming when present
  const ordered = [...trips].sort((a, b) => {
    const an = `${a.vehicle_name || ''}`.includes('LoadTest') ? 0 : 1;
    const bn = `${b.vehicle_name || ''}`.includes('LoadTest') ? 0 : 1;
    return an - bn;
  });

  for (const trip of ordered) {
    const tripId = trip.trip_id || trip.id;
    if (!tripId) continue;
    const layout = fetchTripLayout(config, tripId, authHeaders);
    const items = collectAvailableItems(layout);
    for (const item of items) {
      inventory.push({
        ...item,
        trip_id: item.trip_id || tripId,
        boardingPoint: item.boardingPoint || {
          id: trip.starting_point_id || trip.starting_point || 0,
          name: trip.starting_point || config.tripFrom,
        },
      });
    }
    // Cap setup time — enough for several minutes at ≥5 TPS
    if (inventory.length >= 2000) break;
  }

  return inventory;
}

export function lockItem(config, token, itemId) {
  const res = http.post(
    url('/cart/add', config),
    JSON.stringify({ item_id: itemId }),
    {
      headers: {
        ...jsonHeaders,
        Authorization: `Bearer ${token}`,
      },
      tags: { name: 'cart_lock' },
    },
  );
  lockDuration.add(res.timings.duration);

  const body = parseJson(res);
  const locked = body?.data?.items?.[0] || null;
  const ok = check(res, {
    'lock status 200': (r) => r.status === 200,
    'lock success': () => body && body.success === true && locked && locked.lock_id,
  });

  return ok ? locked : null;
}

export function confirmBooking(config, session, locked, itemMeta) {
  const payload = {
    items: [
      {
        item_id: locked.item_id || itemMeta.item_id,
        lock_id: locked.lock_id,
        type: locked.type || itemMeta.type || 'seat',
        for_self: true,
        passengers: [],
        discount: 0,
        boardingPoint: locked.boardingPoint || itemMeta.boardingPoint || {
          id: 0,
          name: config.tripFrom,
        },
        meta: { cabin_no: locked.cabin_no || itemMeta.cabin_no || '' },
      },
    ],
    customer_name: session.user?.name || 'Load Test',
    customer_mobile: session.user?.mobile || mobileForVu(1, config),
    paid_amount: 0,
    payment_method: 'cash',
    platform: 'android',
  };

  const res = http.post(url('/order/confirm', config), JSON.stringify(payload), {
    headers: {
      ...jsonHeaders,
      Authorization: `Bearer ${session.token}`,
    },
    tags: { name: 'order_confirm' },
  });
  confirmDuration.add(res.timings.duration);

  const body = parseJson(res);
  const ok =
    res.status === 200 &&
    body &&
    body.success === true &&
    (body.order_id || body.data?.id);

  check(res, {
    'confirm status 200': (r) => r.status === 200,
    'confirm success': () => ok,
  });

  if (ok) {
    bookingOk.add(1);
    bookingTps.add(1);
    bookingSuccessRate.add(true);
  } else {
    bookingFail.add(1);
    bookingSuccessRate.add(false);
  }

  return { ok, body, res };
}

/**
 * Full passenger purchase path for one inventory slot.
 * @returns {{ ok: boolean, orderId?: number }}
 */
export function purchaseOnce(config, inventory, iterationIndex, vuId) {
  if (!inventory || inventory.length === 0) {
    bookingFail.add(1);
    bookingSuccessRate.add(false);
    return { ok: false, reason: 'empty_inventory' };
  }

  if (iterationIndex < 0 || iterationIndex >= inventory.length) {
    bookingFail.add(1);
    bookingSuccessRate.add(false);
    return { ok: false, reason: 'inventory_exhausted' };
  }

  const item = inventory[iterationIndex];
  const mobile = mobileForVu(vuId, config);
  const session = login(config, mobile);
  if (!session) {
    bookingFail.add(1);
    bookingSuccessRate.add(false);
    return { ok: false, reason: 'login_failed' };
  }

  const locked = lockItem(config, session.token, item.item_id);
  if (!locked) {
    bookingFail.add(1);
    bookingSuccessRate.add(false);
    return { ok: false, reason: 'lock_failed', item_id: item.item_id };
  }

  const result = confirmBooking(config, session, locked, item);
  return {
    ok: result.ok,
    orderId: result.body?.order_id || result.body?.data?.id,
    item_id: item.item_id,
  };
}

export function setupInventoryOrFail() {
  const config = cfg();
  const bootstrapMobile = mobileForVu(1, config);
  const session = login(config, bootstrapMobile);
  if (!session) {
    fail(
      `Setup login failed for ${bootstrapMobile}. Seed users first: php artisan db:seed --class=TransportLoadTestSeeder (in durpalla)`,
    );
  }

  const inventory = buildInventory(config, session.token);
  if (inventory.length < config.targetTps * 30) {
    console.warn(
      `Low inventory: ${inventory.length} seats. For 2m @ ${config.targetTps} TPS need ~${config.targetTps * 120}. Re-seed with LOADTEST_RESET=1.`,
    );
  }
  if (inventory.length === 0) {
    fail(
      'No bookable seats found. Run TransportLoadTestSeeder and ensure trip_from/trip_to/date match seed data.',
    );
  }

  console.log(
    `Inventory ready: ${inventory.length} items | BASE_URL=${config.baseUrl} | target=${config.targetTps} TPS`,
  );

  return { config, inventory };
}
