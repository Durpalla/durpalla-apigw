/**
 * Closed-loop booking load test (fixed VU pool).
 *
 * Each VU waits for the full purchase flow to finish before starting the next
 * iteration. Throughput ≈ VUs / average_cycle_time — useful for capacity and
 * concurrency behaviour (connection pools, DB locks, seat contention).
 *
 * Seed (Durpalla app):
 *   php artisan db:seed --class=TransportLoadTestSeeder
 *
 * Run:
 *   k6 run -e BASE_URL=http://127.0.0.1:8000 -e CLOSED_VUS=15 loadtests/closed-loop.js
 *   ./loadtests/run.sh closed
 */
import { sleep } from 'k6';
import exec from 'k6/execution';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.4/index.js';
import { cfg } from './lib/config.js';
import { purchaseOnce, setupInventoryOrFail } from './lib/booking.js';

const defaults = cfg();

export const options = {
  scenarios: {
    closed_loop_booking: {
      executor: 'constant-vus',
      vus: defaults.closedVus,
      duration: defaults.duration,
      gracefulStop: '30s',
    },
  },
  thresholds: {
    booking_confirm_success_rate: ['rate>0.90'],
    booking_confirm_duration: ['p(95)<3000', 'p(99)<8000'],
    booking_lock_duration: ['p(95)<2000'],
    http_req_failed: ['rate<0.15'],
    // Closed loop may land under open-loop target if latency rises; track absolute success TPS
    booking_tps: [`rate>=${Math.max(1, Math.min(defaults.targetTps, defaults.targetTps * 0.7))}`],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

export function setup() {
  return setupInventoryOrFail();
}

export default function (data) {
  const config = data.config;
  const inventory = data.inventory;
  const idx = exec.scenario.iterationInTest;
  if (idx >= inventory.length) {
    sleep(1);
    return;
  }

  purchaseOnce(config, inventory, idx, exec.vu.idInTest);

  if (config.thinkTimeMs > 0) {
    sleep(config.thinkTimeMs / 1000);
  }
}

export function handleSummary(data) {
  const confirms = data.metrics.booking_tps?.values?.count || 0;
  const durationSec =
    (data.state?.testRunDurationMs || 0) / 1000 ||
    Number(String(defaults.duration).replace(/m$/, '')) * 60 ||
    120;
  const achieved = durationSec > 0 ? confirms / durationSec : 0;
  const confirmP95 = data.metrics.booking_confirm_duration?.values?.['p(95)'];
  const estVusFor5Tps =
    confirmP95 && confirmP95 > 0 ? Math.ceil((5 * confirmP95) / 1000) : 'n/a';

  console.log('\n========== Closed-loop booking summary ==========');
  console.log(`VUs:           ${defaults.closedVus}`);
  console.log(`Achieved TPS:  ${achieved.toFixed(2)} (successful confirms / wall time)`);
  console.log(`Confirms OK:   ${data.metrics.booking_confirm_ok?.values?.count ?? 0}`);
  console.log(`Confirms FAIL: ${data.metrics.booking_confirm_fail?.values?.count ?? 0}`);
  console.log(`Confirm p95:   ${confirmP95?.toFixed?.(1) ?? 'n/a'} ms`);
  console.log(`Rough VUs for ~5 TPS (if confirm≈cycle): ${estVusFor5Tps}`);
  console.log('If TPS << VUs/latency, check lock contention / DB / PHP workers.');
  console.log('==================================================\n');

  return {
    stdout: textSummary(data, { indent: ' ', enableColors: true }),
  };
}
