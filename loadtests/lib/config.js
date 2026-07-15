/**
 * Shared env config for booking load tests.
 *
 * Required seed (from main Durpalla app):
 *   php artisan db:seed --class=TransportLoadTestSeeder
 */
export function cfg() {
  const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
  const rate = Number(__ENV.TARGET_TPS || 5);
  const duration = __ENV.DURATION || '2m';
  const closedVus = Number(__ENV.CLOSED_VUS || Math.max(10, rate * 2));

  return {
    baseUrl,
    apiPrefix: __ENV.API_PREFIX || '/api/v1',
    tripFrom: __ENV.TRIP_FROM || 'Dhaka',
    tripTo: __ENV.TRIP_TO || 'Khulna',
    tripDate: __ENV.TRIP_DATE || '', // empty = first future loaded from search
    vehicleType: __ENV.VEHICLE_TYPE || 'bus',
    password: __ENV.LOADTEST_PASSWORD || 'LoadTest@123',
    userCount: Number(__ENV.LOADTEST_USER_COUNT || 50),
    userMobileStart: Number(__ENV.LOADTEST_MOBILE_START || 1800000001),
    targetTps: rate,
    duration,
    closedVus,
    preAllocatedVUs: Number(__ENV.PRE_ALLOCATED_VUS || Math.max(20, rate * 4)),
    maxVUs: Number(__ENV.MAX_VUS || Math.max(50, rate * 10)),
    thinkTimeMs: Number(__ENV.THINK_TIME_MS || 0),
  };
}

export function mobileForVu(vuId, config) {
  const index = ((vuId - 1) % config.userCount) + 1;
  const n = config.userMobileStart + index - 1;
  return String(n).padStart(11, '0');
}
