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

bash "${DEPLOY_SCRIPT_DIR:-$(dirname "$0")}/normalize-docker-env.sh" "$DEPLOY_PATH"

docker_redis_env=()
if ! grep -q '^REDIS_SENTINEL_ENABLED=true' "$DEPLOY_PATH/.env"; then
  docker_redis_env+=( -e REDIS_HOST=host.docker.internal )
fi

if ! printf '%s' "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin; then
  echo "ERROR: docker login ghcr.io failed."
  echo "Check GHCR_PULL_TOKEN secret (PAT with read:packages) and package visibility for ${IMAGE%%:*}."
  exit 1
fi

docker volume inspect apigw-storage >/dev/null 2>&1 || docker volume create apigw-storage
docker volume inspect apigw-bootstrap-cache >/dev/null 2>&1 || docker volume create apigw-bootstrap-cache

docker pull "$IMAGE"
echo "Image pulled: $IMAGE"
docker tag "$IMAGE" durpalla-apigw-app:local

echo "Recreating containers..."
for c in durpalla-apigw-1 durpalla-apigw-2 durpalla-apigw-3 durpalla-apigw-4; do
  docker rm -f "$c" >/dev/null 2>&1 || true
done

common_run=(
  -d --restart unless-stopped
  --add-host=host.docker.internal:host-gateway
  --env-file "$DEPLOY_PATH/.env"
  "${docker_redis_env[@]}"
  -v apigw-storage:/var/www/html/storage
  -v apigw-bootstrap-cache:/var/www/html/bootstrap/cache
  durpalla-apigw-app:local
)

docker run --name durpalla-apigw-1 -e APIGW_PRIMARY=1 -p 8001:80 "${common_run[@]}"
docker run --name durpalla-apigw-2 -p 8002:80 "${common_run[@]}"
docker run --name durpalla-apigw-3 -p 8003:80 "${common_run[@]}"
docker run --name durpalla-apigw-4 -p 8004:80 "${common_run[@]}"

echo "Warming Laravel caches..."
artisan() {
  docker exec -T durpalla-apigw-1 "$@"
}

artisan php artisan config:clear
artisan php artisan route:clear
artisan php artisan view:clear

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

echo "Pruning unused Docker images..."
if command -v timeout >/dev/null 2>&1; then
  timeout 120 docker image prune -f || true
else
  docker image prune -f || true
fi

echo "Deployed ${IMAGE} on $(hostname) ports 8001-8004"
