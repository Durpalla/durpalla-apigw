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

if grep -q 'host\.docker\.internal' "$DEPLOY_PATH/.env"; then
  :
else
  sed -i 's/^DB_HOST=localhost$/DB_HOST=host.docker.internal/' "$DEPLOY_PATH/.env"
  sed -i 's/^DB_HOST=127.0.0.1$/DB_HOST=host.docker.internal/' "$DEPLOY_PATH/.env"
  sed -i 's/^REDIS_HOST=localhost$/REDIS_HOST=host.docker.internal/' "$DEPLOY_PATH/.env"
  sed -i 's/^REDIS_HOST=127.0.0.1$/REDIS_HOST=host.docker.internal/' "$DEPLOY_PATH/.env"
  sed -i 's/^MONGODB_HOST=localhost$/MONGODB_HOST=host.docker.internal/' "$DEPLOY_PATH/.env"
  sed -i 's/^MONGODB_HOST=127.0.0.1$/MONGODB_HOST=host.docker.internal/' "$DEPLOY_PATH/.env"
fi
sed -i '/^DB_SOCKET=/d' "$DEPLOY_PATH/.env"

if ! printf '%s' "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin; then
  echo "ERROR: docker login ghcr.io failed."
  echo "Check GHCR_PULL_TOKEN secret (PAT with read:packages) and package visibility for ${IMAGE%%:*}."
  exit 1
fi

docker volume inspect apigw-storage >/dev/null 2>&1 || docker volume create apigw-storage
docker volume inspect apigw-bootstrap-cache >/dev/null 2>&1 || docker volume create apigw-bootstrap-cache

docker pull "$IMAGE"
docker tag "$IMAGE" durpalla-apigw-app:local

for c in durpalla-apigw-1 durpalla-apigw-2 durpalla-apigw-3 durpalla-apigw-4; do
  docker rm -f "$c" >/dev/null 2>&1 || true
done

common_run=(
  -d --restart unless-stopped
  --add-host=host.docker.internal:host-gateway
  --env-file "$DEPLOY_PATH/.env"
  -v apigw-storage:/var/www/html/storage
  -v apigw-bootstrap-cache:/var/www/html/bootstrap/cache
  durpalla-apigw-app:local
)

docker run --name durpalla-apigw-1 -e APIGW_PRIMARY=1 -p 8001:80 "${common_run[@]}"
docker run --name durpalla-apigw-2 -p 8002:80 "${common_run[@]}"
docker run --name durpalla-apigw-3 -p 8003:80 "${common_run[@]}"
docker run --name durpalla-apigw-4 -p 8004:80 "${common_run[@]}"

docker exec durpalla-apigw-1 php artisan config:clear
docker exec durpalla-apigw-1 php artisan route:clear
docker exec durpalla-apigw-1 php artisan view:clear
docker exec durpalla-apigw-1 php artisan config:cache
docker exec durpalla-apigw-1 php artisan route:cache
docker exec durpalla-apigw-1 php artisan view:cache
docker exec durpalla-apigw-1 php artisan cache:clear || true

docker image prune -f
echo "Deployed ${IMAGE} on $(hostname) ports 8001-8004"
