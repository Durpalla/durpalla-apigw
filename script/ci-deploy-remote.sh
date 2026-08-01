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

# Pin every container to this digest so a later retag of :local cannot leave stragglers.
EXPECTED_IMAGE_ID="$(docker image inspect -f '{{.Id}}' "$IMAGE")"
if [[ -z "$EXPECTED_IMAGE_ID" ]]; then
  echo "ERROR: could not resolve image id for $IMAGE"
  exit 1
fi
echo "Expected image id: $EXPECTED_IMAGE_ID"

# Drop any leftover / renamed apigw app containers that are not the canonical 1–4 names.
echo "Removing orphan apigw containers (not durpalla-apigw-1..4)..."
while read -r orphan; do
  [[ -z "$orphan" ]] && continue
  echo "  rm -f orphan $orphan"
  docker rm -f "$orphan" >/dev/null 2>&1 || true
done < <(docker ps -a --format '{{.Names}}' | grep -E '^durpalla-apigw' | grep -Ev '^durpalla-apigw-[1-4]$' || true)

common_run=(
  -d --restart unless-stopped
  --add-host="host.docker.internal:${DOCKER_HOST_GATEWAY}"
  -v "$DEPLOY_PATH/.env:/var/www/html/.env:ro"
  "${docker_redis_env[@]}"
  -v apigw-storage:/var/www/html/storage
  -v apigw-bootstrap-cache:/var/www/html/bootstrap/cache
  "${shared_public_mounts[@]}"
  # Unbounded json-file logs across 4 containers previously filled /var/lib/docker.
  --log-opt max-size=50m
  --log-opt max-file=2
  # Steady state per container is well under 100MB. The ceiling exists so a runaway
  # process is killed inside its own cgroup instead of pushing the 4GB host into OOM.
  # memory-swap equal to memory keeps a container from spilling into swap.
  --memory=1g
  --memory-swap=1g
  # Use the digest-resolved image id, not a mutable :local tag.
  "$EXPECTED_IMAGE_ID"
)

container_image_id() {
  docker inspect -f '{{.Image}}' "$1" 2>/dev/null || true
}

assert_container_image() {
  local name="$1"
  local got
  got="$(container_image_id "$name")"
  if [[ "$got" != "$EXPECTED_IMAGE_ID" ]]; then
    echo "ERROR: ${name} image mismatch."
    echo "  expected: $EXPECTED_IMAGE_ID"
    echo "  got:      ${got:-<missing>}"
    return 1
  fi
  return 0
}

replace_apigw_container() {
  local idx="$1"
  local port="$2"
  local name="durpalla-apigw-${idx}"
  local primary_env=()

  if [[ "$idx" == "1" ]]; then
    primary_env=( -e APIGW_PRIMARY=1 )
  fi

  echo "Rolling replace ${name} on port ${port} (other backends keep serving)..."
  # Force-destroy whatever currently owns this name/port — never leave an old replica.
  docker rm -f "$name" >/dev/null 2>&1 || true
  # If an unnamed/old container still holds the published port, kill it.
  local port_holder
  port_holder="$(docker ps --filter "publish=${port}" --format '{{.ID}} {{.Names}}' | head -1 || true)"
  if [[ -n "$port_holder" ]]; then
    echo "  Clearing port ${port} holder: ${port_holder}"
    docker rm -f "$(awk '{print $1}' <<<"$port_holder")" >/dev/null 2>&1 || true
  fi

  # Bound to loopback: the host nginx proxies to 127.0.0.1:<port>, so publishing on all
  # interfaces only exposed the backends directly to the internet.
  # shellcheck disable=SC2068
  docker run --name "$name" -p "127.0.0.1:${port}:80" "${primary_env[@]}" "${common_run[@]}"

  for attempt in $(seq 1 30); do
    if curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; then
      assert_container_image "$name" || exit 1
      echo "  OK ${name} — http://127.0.0.1:${port}/up (image verified)"
      return 0
    fi
    echo "  Waiting for ${name} (attempt ${attempt}/30)..."
    sleep 2
  done

  echo "ERROR: ${name} failed health check on port ${port}"
  docker logs "$name" --tail 60 2>&1 || true
  exit 1
}

reconcile_all_apigw_containers() {
  echo "Reconciling durpalla-apigw-1..4 to ${EXPECTED_IMAGE_ID}..."
  local idx port name got
  for idx in 1 2 3 4; do
    port=$((8000 + idx))
    name="durpalla-apigw-${idx}"
    got="$(container_image_id "$name")"
    if [[ "$got" != "$EXPECTED_IMAGE_ID" ]]; then
      echo "  ${name} is stale (${got:-missing}) — force replace"
      replace_apigw_container "$idx" "$port"
    else
      echo "  ${name} already on expected image"
    fi
  done
}

echo "Rolling deploy: replace one container at a time on ports 8001-8004..."
replace_apigw_container 1 8001

if ! docker exec durpalla-apigw-1 test -f /var/www/html/app/Providers/OpenTelemetryServiceProvider.php; then
  echo "ERROR: app/Providers/OpenTelemetryServiceProvider.php missing in ${IMAGE}."
  echo "Debug: docker exec durpalla-apigw-1 ls -la /var/www/html/app/Providers/ | head -40"
  docker exec durpalla-apigw-1 ls -la /var/www/html/app/Providers/ 2>&1 | head -40 || true
  exit 1
fi

if ! docker exec durpalla-apigw-1 getent hosts host.docker.internal >/dev/null 2>&1; then
  echo "ERROR: host.docker.internal is not resolvable inside durpalla-apigw-1"
  exit 1
fi

echo "Warming Laravel caches on primary (shared bootstrap volume)..."
artisan() {
  docker exec durpalla-apigw-1 "$@"
}

echo "Ensuring Passport OAuth keys on persistent storage..."
if ! artisan php script/ensure-passport-keys.php; then
  echo "ERROR: Passport keys are missing or invalid."
  exit 1
fi

artisan php artisan config:clear
artisan php artisan route:clear
artisan php artisan view:clear
artisan sh -c 'rm -f bootstrap/cache/services.php bootstrap/cache/packages.php 2>/dev/null || true'

echo "Clearing app cache (array driver — skip Redis during deploy)..."
if command -v timeout >/dev/null 2>&1; then
  timeout 30 docker exec -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
else
  docker exec -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
fi

artisan php artisan config:cache
artisan php artisan route:cache
artisan php artisan view:cache

replace_apigw_container 2 8002
replace_apigw_container 3 8003
replace_apigw_container 4 8004

# Catch any straggler left by a previous interrupted deploy.
reconcile_all_apigw_containers

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

echo "Final gate: all four containers must be healthy AND on the expected image..."
health_fail=0
for idx in 1 2 3 4; do
  port=$((8000 + idx))
  name="durpalla-apigw-${idx}"
  if ! curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; then
    echo "  FAIL ${name} http://127.0.0.1:${port}/up"
    health_fail=1
    continue
  fi
  if ! assert_container_image "$name"; then
    health_fail=1
    continue
  fi
  echo "  OK ${name} port ${port} image match"
done

if [[ "$health_fail" -ne 0 ]]; then
  echo "ERROR: Not all apigw containers are healthy on the new image."
  docker ps -a --filter name=durpalla-apigw --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}' || true
  docker logs durpalla-apigw-1 --tail 60 2>&1 || true
  exit 1
fi

echo "Running containers:"
docker ps --filter name=durpalla-apigw --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'

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
