/**
 * Open-loop booking load test (constant arrival rate).
 *
 * Starts a new purchase iteration at a fixed rate regardless of latency.
 * Use this to force ≥ TARGET_TPS and observe queues, timeouts, and bottlenecks.
 *
 * Seed (Durpalla app):
 *   php artisan db:seed --class=TransportLoadTestSeeder
 *   LOADTEST_RESET=1 php artisan db:seed --class=TransportLoadTestSeeder
 *
 * Run:
 *   k6 run -e BASE_URL=http://127.0.0.1:8000 -e TARGET_TPS=5 loadtests/open-loop.js
 *   ./loadtests/run.sh open
 */
import { sleep } from 'k6';
import exec from 'k6/execution';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.4/index.js';
import { cfg } from './lib/config.js';
import { purchaseOnce, setupInventoryOrFail } from './lib/booking.js';

const defaults = cfg();

export const options = {
  scenarios: {
    open_loop_booking: {
      executor: 'constant-arrival-rate',
      rate: defaults.targetTps,
      timeUnit: '1s',
      duration: defaults.duration,
      preAllocatedVUs: defaults.preAllocatedVUs,
      maxVUs: defaults.maxVUs,
      gracefulStop: '30s',
    },
  },
  thresholds: {
    booking_confirm_success_rate: ['rate>0.90'],
    booking_confirm_duration: ['p(95)<3000', 'p(99)<8000'],
    booking_lock_duration: ['p(95)<2000'],
    http_req_failed: ['rate<0.15'],
    booking_tps: [`rate>=${Math.max(1, defaults.targetTps * 0.9)}`],
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

  console.log('\n========== Open-loop booking summary ==========');
  console.log(`Target TPS:    ${defaults.targetTps}`);
  console.log(`Achieved TPS:  ${achieved.toFixed(2)} (successful confirms / wall time)`);
  console.log(`Confirms OK:   ${data.metrics.booking_confirm_ok?.values?.count ?? 0}`);
  console.log(`Confirms FAIL: ${data.metrics.booking_confirm_fail?.values?.count ?? 0}`);
  console.log(
    `Confirm p95:   ${data.metrics.booking_confirm_duration?.values?.['p(95)']?.toFixed?.(1) ?? 'n/a'} ms`,
  );
  console.log('Compare search / lock / confirm trends above to spot bottlenecks.');
  console.log('================================================\n');

  return {
    stdout: textSummary(data, { indent: ' ', enableColors: true }),
  };
}
