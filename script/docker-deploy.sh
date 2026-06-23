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

bash "$ROOT/script/normalize-docker-env.sh" "$DEPLOY_PATH"

docker_redis_env=()
if ! grep -q '^REDIS_SENTINEL_ENABLED=true' "$DEPLOY_PATH/.env"; then
  docker_redis_env+=( -e REDIS_HOST=host.docker.internal )
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

run_artisan() {
  docker exec -T durpalla-apigw-1 "$@"
}

run_artisan php artisan config:clear
run_artisan php artisan route:clear
run_artisan php artisan view:clear
run_artisan php artisan config:cache
run_artisan php artisan route:cache
run_artisan php artisan view:cache

echo "Clearing app cache (array driver — skip Redis during deploy)..."
if command -v timeout >/dev/null 2>&1; then
  timeout 30 docker exec -T -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
else
  docker exec -T -e CACHE_STORE=array durpalla-apigw-1 php artisan cache:clear || true
fi

docker image prune -f >/dev/null 2>&1 || true

echo "Deployed 4 containers:"
echo "  http://127.0.0.1:8001 (primary: queue + scheduler)"
echo "  http://127.0.0.1:8002"
echo "  http://127.0.0.1:8003"
echo "  http://127.0.0.1:8004"
echo "Host nginx upstream should proxy_pass to these ports."
