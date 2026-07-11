#!/usr/bin/env bash
# Remote deploy script — invoked by GitHub Actions over SSH on each app server.
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"
IMAGE="${IMAGE:?IMAGE is required}"
GHCR_USER="${GHCR_USER:?GHCR_USER is required}"

if [[ -z "${GHCR_TOKEN_B64:-}" ]]; then
  echo "ERROR: GHCR token is empty on server."
  exit 1
fi

GHCR_TOKEN="$(printf '%s' "$GHCR_TOKEN_B64" | base64 -d 2>/dev/null || printf '%s' "$GHCR_TOKEN_B64" | base64 -D)"
if [[ -z "$GHCR_TOKEN" ]]; then
  echo "ERROR: Failed to decode GHCR token."
  exit 1
fi

if [[ ! -d "$DEPLOY_PATH" ]]; then
  echo "ERROR: $DEPLOY_PATH does not exist."
  echo "Bootstrap once: sudo mkdir -p $DEPLOY_PATH && sudo chown -R \$(whoami):\$(whoami) $DEPLOY_PATH"
  exit 1
fi

if [[ ! -w "$DEPLOY_PATH" ]]; then
  echo "ERROR: $DEPLOY_PATH is not writable by $(whoami)"
  exit 1
fi

cd "$DEPLOY_PATH"

if [[ ! -f .env ]]; then
  echo "Missing $DEPLOY_PATH/.env — create it before first deploy"
  exit 1
fi

if ! grep -qE '^APP_KEY=base64:.+' "$DEPLOY_PATH/.env"; then
  echo "ERROR: APP_KEY is missing or invalid in $DEPLOY_PATH/.env"
  echo "Generate once on the server: php artisan key:generate --show"
  exit 1
fi

uses_redis_cache="$(grep -E '^CACHE_STORE=redis$|^QUEUE_CONNECTION=redis$|^SESSION_DRIVER=redis$' "$DEPLOY_PATH/.env" || true)"
if [[ -n "$uses_redis_cache" ]]; then
  redis_password="$(grep -E '^REDIS_PASSWORD=' "$DEPLOY_PATH/.env" | cut -d= -f2- || true)"
  if [[ -z "$redis_password" || "$redis_password" == "null" ]]; then
    echo "ERROR: REDIS_PASSWORD must be set when CACHE_STORE, QUEUE_CONNECTION, or SESSION_DRIVER uses redis."
    exit 1
  fi
fi

SCRIPT_DIR="${DEPLOY_SCRIPT_DIR:-$(dirname "$0")}"
export DOCKER_HOST_GATEWAY="$(bash "${SCRIPT_DIR}/docker-host-gateway.sh")"
echo "Docker host gateway: ${DOCKER_HOST_GATEWAY}"

bash "${SCRIPT_DIR}/normalize-docker-env.sh" "$DEPLOY_PATH"

docker_redis_env=()
if ! grep -q '^REDIS_SENTINEL_ENABLED=true' "$DEPLOY_PATH/.env"; then
  docker_redis_env+=( -e "REDIS_HOST=${DOCKER_HOST_GATEWAY}" )
fi

if ! printf '%s' "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin; then
  echo "ERROR: docker login ghcr.io failed."
  echo "Check GHCR_PULL_TOKEN secret (PAT with read:packages) and package visibility for ${IMAGE%%:*}."
  exit 1
fi

docker volume inspect apigw-storage >/dev/null 2>&1 || docker volume create apigw-storage
docker volume inspect apigw-bootstrap-cache >/dev/null 2>&1 || docker volume create apigw-bootstrap-cache

SHARED_ASSETS_ROOT="${SHARED_ASSETS_ROOT:-/mnt/durpalla-assets}"
shared_public_mounts=()
if [[ -d "${SHARED_ASSETS_ROOT}/uploads" ]]; then
  echo "Using shared assets at ${SHARED_ASSETS_ROOT}"
  for dir in uploads nid vehicles qrs images temp; do
    if [[ -d "${SHARED_ASSETS_ROOT}/${dir}" ]]; then
      shared_public_mounts+=( -v "${SHARED_ASSETS_ROOT}/${dir}:/var/www/html/public/${dir}" )
    fi
  done
else
  echo "Shared assets not mounted — apigw public uploads stay in container filesystem"
fi

docker pull "$IMAGE"
echo "Image pulled: $IMAGE"
docker tag "$IMAGE" durpalla-apigw-app:local

echo "Recreating containers..."
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
  "${shared_public_mounts[@]}"
  durpalla-apigw-app:local
)

docker run --name durpalla-apigw-1 -e APIGW_PRIMARY=1 -p 8001:80 "${common_run[@]}"
docker run --name durpalla-apigw-2 -p 8002:80 "${common_run[@]}"
docker run --name durpalla-apigw-3 -p 8003:80 "${common_run[@]}"
docker run --name durpalla-apigw-4 -p 8004:80 "${common_run[@]}"

if ! docker exec -T durpalla-apigw-1 test -f app/Providers/OpenTelemetryServiceProvider.php; then
  echo "ERROR: app/Providers/OpenTelemetryServiceProvider.php missing in ${IMAGE}."
  echo "Pull/rebuild the latest apigw image (OpenTelemetry support requires a current build)."
  exit 1
fi

if ! docker exec -T durpalla-apigw-1 getent hosts host.docker.internal >/dev/null 2>&1; then
  echo "ERROR: host.docker.internal is not resolvable inside durpalla-apigw-1"
  exit 1
fi

echo "Warming Laravel caches..."
artisan() {
  docker exec -T durpalla-apigw-1 "$@"
}

echo "Ensuring Passport OAuth keys on persistent storage..."
if ! artisan php script/ensure-passport-keys.php; then
  echo "ERROR: Passport keys are missing or invalid."
  echo "Set PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY in $DEPLOY_PATH/.env"
  echo "or restore storage/oauth-*.key on the apigw-storage Docker volume."
  exit 1
fi

artisan php artisan config:clear
artisan php artisan route:clear
artisan php artisan view:clear
artisan sh -c 'rm -f bootstrap/cache/services.php bootstrap/cache/packages.php 2>/dev/null || true'

# Must run before config:cache — cached config ignores CACHE_STORE env overrides.
echo "Clearing app cache (array driver — skip Redis during deploy)..."
if command -v timeout >/dev/null 2>&1; then
  timeout 30 docker exec -T -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
else
  docker exec -T -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
fi

artisan php artisan config:cache
artisan php artisan route:cache
artisan php artisan view:cache

echo "Verifying MySQL..."
mysql_ok="$(artisan php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
  Illuminate\Support\Facades\DB::connection()->getPdo();
  echo 'OK';
} catch (Throwable \$e) {
  echo 'ERR:'.\$e->getMessage();
}
" 2>/dev/null | grep -oE '^OK$|^ERR:.+' | tail -1 || true)"
if [[ "$mysql_ok" != "OK" ]]; then
  echo "ERROR: MySQL check failed after deploy: ${mysql_ok:-empty}"
  echo "DB settings from .env:"
  grep -E '^(DB_HOST|DB_PORT|DB_ROUTER_HOST)=' "$DEPLOY_PATH/.env" || true
  echo "Re-run: bash ${DEPLOY_PATH}/script/normalize-docker-env.sh ${DEPLOY_PATH}"
  exit 1
fi
echo "MySQL OK"

echo "Verifying Passport can load OAuth keys..."
if ! artisan php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$private = storage_path('oauth-private.key');
\$public = storage_path('oauth-public.key');
if (!is_readable(\$private) || !is_readable(\$public)) {
    fwrite(STDERR, 'oauth key files not readable after config:cache'.PHP_EOL);
    exit(1);
}
if (!openssl_pkey_get_private(file_get_contents(\$private))) {
    fwrite(STDERR, 'invalid oauth-private.key'.PHP_EOL);
    exit(1);
}
if (!openssl_pkey_get_public(file_get_contents(\$public))) {
    fwrite(STDERR, 'invalid oauth-public.key'.PHP_EOL);
    exit(1);
}
"; then
  echo "ERROR: Passport key verification failed after config:cache."
  exit 1
fi

# Route/config caches are loaded by PHP-FPM workers; restart so opcache picks up new files.
echo "Restarting containers to apply route/config cache..."
for c in durpalla-apigw-1 durpalla-apigw-2 durpalla-apigw-3 durpalla-apigw-4; do
  docker restart "$c" >/dev/null
done
sleep 2
health_ok=0
for port in 8001 8002 8003 8004; do
  if curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; then
    echo "  OK 127.0.0.1:${port}/up"
    health_ok=1
  else
    echo "  FAIL 127.0.0.1:${port}/up not responding after restart"
  fi
done

if [[ "$health_ok" -ne 1 ]]; then
  echo "ERROR: No apigw container responded on /up"
  echo "--- durpalla-apigw-1 logs (last 60 lines) ---"
  docker logs durpalla-apigw-1 --tail 60 2>&1 || true
  echo "--- laravel.log (last 40 lines) ---"
  docker exec -T durpalla-apigw-1 sh -c 'tail -40 storage/logs/laravel.log 2>/dev/null || echo "(no laravel.log)"' || true
  exit 1
fi

# Host nginx is configured once on the server — CI only deploys containers.
if curl -fsS -H 'Host: apigw.durpalla.com' 'http://127.0.0.1/up' >/dev/null 2>&1; then
  echo "  OK host nginx -> apigw upstream"
else
  echo "  WARN host nginx not proxying apigw.durpalla.com (run setup-host-nginx.sh once if needed)"
fi

echo "Pruning unused Docker images..."
if command -v timeout >/dev/null 2>&1; then
  timeout 120 docker image prune -f || true
else
  docker image prune -f || true
fi

# Remove stale GHCR apigw tags (keep the image the running containers use).
IN_USE_ID="$(docker inspect -f '{{.Image}}' durpalla-apigw-1 2>/dev/null || true)"
docker images "${IMAGE%%:*}" --format '{{.ID}} {{.Repository}}:{{.Tag}}' \
  | while read -r img_id img_ref; do
      if [[ -n "$IN_USE_ID" && "$img_id" != "$IN_USE_ID" ]]; then
        docker rmi -f "$img_ref" 2>/dev/null || true
      fi
    done
docker image prune -f || true

echo "Deployed ${IMAGE} on $(hostname) ports 8001-8004"
df -h /
