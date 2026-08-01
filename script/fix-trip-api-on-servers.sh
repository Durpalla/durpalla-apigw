#!/usr/bin/env bash
# Diagnose + fix intermittent /api/v1/trip/{id} 500/404 on apigw hosts.
# Usage:
#   DEPLOY_USER=durpalla bash script/fix-trip-api-on-servers.sh
#   bash script/fix-trip-api-on-servers.sh 103.60.204.238
set -euo pipefail

SSH_USER="${DEPLOY_USER:-durpalla}"
SSH_PORT="${DEPLOY_PORT:-22}"
DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"
TRIP_ID="${TRIP_ID:-10}"

if [[ $# -gt 0 ]]; then
  SERVERS=("$@")
else
  SERVERS=(103.60.204.238 103.60.204.94)
fi

SSH_OPTS=(
  -o BatchMode=yes
  -o StrictHostKeyChecking=accept-new
  -o ConnectTimeout=20
  -o ServerAliveInterval=15
  -p "$SSH_PORT"
)

remote_fix() {
  local host="$1"
  echo
  echo "========== ${SSH_USER}@${host} =========="

  if ! ssh "${SSH_OPTS[@]}" "${SSH_USER}@${host}" "echo reachable; hostname; uptime"; then
    echo "FAIL: cannot SSH to ${host}"
    return 1
  fi

  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${host}" \
    DEPLOY_PATH="$DEPLOY_PATH" TRIP_ID="$TRIP_ID" \
    'bash -s' <<'REMOTE'
set -euo pipefail

echo "--- docker ---"
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}' | head -30 || true

APP_C="$(docker ps --format '{{.Names}}' | grep -E 'apigw' | grep -v nginx | head -1 || true)"
NGINX_C="$(docker ps --format '{{.Names}}' | grep -E 'nginx|apigw' | head -1 || true)"
echo "APP_C=${APP_C:-none}"

if [[ -z "${APP_C:-}" ]]; then
  echo "ERROR: no apigw app container running"
  if [[ -d "$DEPLOY_PATH" ]]; then
    cd "$DEPLOY_PATH"
    docker compose ps || true
  fi
  exit 2
fi

echo "--- image ---"
docker inspect "$APP_C" --format '{{.Config.Image}} created={{.Created}}' || true

echo "--- clear caches ---"
docker exec "$APP_C" php artisan route:clear --no-interaction || true
docker exec "$APP_C" php artisan config:clear --no-interaction || true
docker exec "$APP_C" php artisan cache:clear --no-interaction || true
docker exec "$APP_C" php artisan optimize:clear --no-interaction || true

echo "--- routes (trip) ---"
docker exec "$APP_C" php artisan route:list --path=trip --no-interaction 2>/dev/null | head -40 || true

echo "--- local curl trip/${TRIP_ID} ---"
# App containers typically publish 127.0.0.1:8001–8004 → 80
code="000"
for port in 8001 8002 8003 8004; do
  url="http://127.0.0.1:${port}/api/v1/trip/${TRIP_ID}"
  echo "GET $url"
  code=$(curl -sS -m 25 -o /tmp/trip-body.json -w '%{http_code}' \
    -H 'Accept: application/json' "$url" 2>/dev/null || echo "000")
  echo "HTTP:$code"
  head -c 600 /tmp/trip-body.json 2>/dev/null || true
  echo
  if [[ "$code" == "200" ]]; then
    break
  fi
done

# If still 500, print exception from logs
if [[ "${code:-}" != "200" ]]; then
  echo "--- recent container logs ---"
  docker logs --tail 120 "$APP_C" 2>&1 | grep -iE 'trip|Unable to load|ERROR|Exception|Fatal|OOM|SQLSTATE' | tail -40 || true
  docker logs --tail 80 "$APP_C" 2>&1 | tail -40 || true
fi

# Soft restart app containers to pick up latest image env / clear stuck workers
echo "--- recreate apigw app containers (compose) ---"
if [[ -f "$DEPLOY_PATH/docker-compose.yml" ]] || [[ -f "$DEPLOY_PATH/compose.yml" ]]; then
  cd "$DEPLOY_PATH"
  # Pull is optional; prefer restart of current image first
  docker compose up -d --remove-orphans 2>/dev/null \
    || docker-compose up -d --remove-orphans 2>/dev/null \
    || true
  sleep 3
  APP_C="$(docker ps --format '{{.Names}}' | grep -E 'apigw' | grep -v nginx | head -1 || true)"
  if [[ -n "${APP_C:-}" ]]; then
    docker exec "$APP_C" php artisan route:clear --no-interaction || true
    docker exec "$APP_C" php artisan config:clear --no-interaction || true
  fi
fi

echo "--- retest trip/${TRIP_ID} on :8001–8004 ---"
for port in 8001 8002 8003 8004; do
  code=$(curl -sS -m 25 -o /tmp/trip-body2.json -w '%{http_code}' \
    -H 'Accept: application/json' \
    "http://127.0.0.1:${port}/api/v1/trip/${TRIP_ID}" 2>/dev/null || echo "000")
  echo "port ${port} HTTP:${code}"
  if [[ "$code" != "200" ]]; then
    head -c 400 /tmp/trip-body2.json 2>/dev/null || true
    echo
  fi
done
echo "DONE ${HOSTNAME:-host}"
REMOTE
}

fail=0
for host in "${SERVERS[@]}"; do
  if ! remote_fix "$host"; then
    fail=1
  fi
done

echo
echo "Public check (from this machine):"
curl -sS -m 20 -w '\nHTTP:%{http_code}\n' -H 'Accept: application/json' \
  "https://apigw.durpalla.com/api/v1/trip/${TRIP_ID}" | head -c 800 || true
echo

exit "$fail"
