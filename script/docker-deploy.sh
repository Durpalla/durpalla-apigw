#!/usr/bin/env bash
# Deploy 4 HTTP containers for host nginx proxy_pass (8001–8004).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"
IMAGE="${IMAGE:-durpalla-apigw-app:local}"

if [[ ! -d "$DEPLOY_PATH" ]]; then
  echo "ERROR: $DEPLOY_PATH does not exist. Create it and add .env first." >&2
  exit 1
fi

cd "$DEPLOY_PATH"

if [[ ! -f .env ]]; then
  echo "ERROR: Missing $DEPLOY_PATH/.env" >&2
  exit 1
fi

export DOCKER_HOST_GATEWAY="$(bash "$ROOT/script/docker-host-gateway.sh")"
echo "Docker host gateway: ${DOCKER_HOST_GATEWAY}"

bash "$ROOT/script/normalize-docker-env.sh" "$DEPLOY_PATH"

docker_redis_env=()
if ! grep -q '^REDIS_SENTINEL_ENABLED=true' "$DEPLOY_PATH/.env"; then
  docker_redis_env+=( -e "REDIS_HOST=${DOCKER_HOST_GATEWAY}" )
fi

docker volume inspect apigw-storage >/dev/null 2>&1 || docker volume create apigw-storage
docker volume inspect apigw-bootstrap-cache >/dev/null 2>&1 || docker volume create apigw-bootstrap-cache

if [[ "$IMAGE" == "durpalla-apigw-app:local" ]]; then
  docker build -t "$IMAGE" "$DEPLOY_PATH"
else
  docker pull "$IMAGE"
  docker tag "$IMAGE" durpalla-apigw-app:local
fi

for c in durpalla-apigw-1 durpalla-apigw-2 durpalla-apigw-3 durpalla-apigw-4; do
  docker rm -f "$c" >/dev/null 2>&1 || true
done

common_run=(
  -d --restart unless-stopped
  --add-host="host.docker.internal:${DOCKER_HOST_GATEWAY}"
  # Bind-mount .env so multiline PASSPORT_*_KEY values work (docker --env-file cannot parse them).
  -v "$DEPLOY_PATH/.env:/var/www/html/.env:ro"
  "${docker_redis_env[@]}"
  -v apigw-storage:/var/www/html/storage
  -v apigw-bootstrap-cache:/var/www/html/bootstrap/cache
  durpalla-apigw-app:local
)

docker run --name durpalla-apigw-1 -e APIGW_PRIMARY=1 -p 8001:80 "${common_run[@]}"
docker run --name durpalla-apigw-2 -p 8002:80 "${common_run[@]}"
docker run --name durpalla-apigw-3 -p 8003:80 "${common_run[@]}"
docker run --name durpalla-apigw-4 -p 8004:80 "${common_run[@]}"

if ! docker exec durpalla-apigw-1 test -f /var/www/html/app/Providers/OpenTelemetryServiceProvider.php; then
  echo "ERROR: app/Providers/OpenTelemetryServiceProvider.php missing in ${IMAGE}." >&2
  echo "Pull/rebuild the latest apigw image." >&2
  exit 1
fi

run_artisan() {
  docker exec durpalla-apigw-1 "$@"
}

echo "Ensuring Passport OAuth keys on persistent storage..."
run_artisan php artisan config:clear
bash "$ROOT/script/seed-passport-keys-volume.sh"
if ! run_artisan php script/ensure-passport-keys.php; then
  echo "ERROR: Passport keys are missing or invalid." >&2
  echo "Set PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY in $DEPLOY_PATH/.env," >&2
  echo "set DURPALLA_PASSPORT_KEYS_DIR to main app storage/, or restore oauth-*.key on apigw-storage." >&2
  exit 1
fi

run_artisan php artisan config:clear
run_artisan php artisan route:clear
run_artisan php artisan view:clear

echo "Clearing app cache (array driver — skip Redis during deploy)..."
if command -v timeout >/dev/null 2>&1; then
  timeout 30 docker exec -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
else
  docker exec -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
fi

run_artisan php artisan config:cache
run_artisan php artisan route:cache
run_artisan php artisan view:cache

# opcache.validate_timestamps=0 — recycle so PHP-FPM loads the new bootstrap cache.
echo "Recycling containers to pick up warmed bootstrap cache..."
docker restart durpalla-apigw-1 durpalla-apigw-2 durpalla-apigw-3 durpalla-apigw-4 >/dev/null
for port in 8001 8002 8003 8004; do
  for attempt in $(seq 1 30); do
    if curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
done

echo "Verifying Passport can load OAuth keys..."
if ! run_artisan php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$private = storage_path('oauth-private.key');
\$public = storage_path('oauth-public.key');
if (!is_readable(\$private) || !is_readable(\$public)) {
    fwrite(STDERR, 'oauth key files not readable after config:cache'.PHP_EOL);
    exit(1);
}
"; then
  echo "ERROR: Passport key verification failed after config:cache." >&2
  exit 1
fi

docker image prune -f >/dev/null 2>&1 || true

echo "Deployed 4 containers:"
echo "  http://127.0.0.1:8001 (primary: queue + scheduler)"
echo "  http://127.0.0.1:8002"
echo "  http://127.0.0.1:8003"
echo "  http://127.0.0.1:8004"
echo "Host nginx upstream should proxy_pass to these ports."
