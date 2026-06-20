#!/usr/bin/env bash
# Manual deploy on the server (same pattern as billman / larabill).
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"
IMAGE="${IMAGE:-durpalla-apigw-app:local}"
APIGW_FPM_PORT="${APIGW_FPM_PORT:-9005}"

if [[ ! -d "$DEPLOY_PATH" ]]; then
  echo "ERROR: $DEPLOY_PATH does not exist. Create it and add .env first." >&2
  exit 1
fi

cd "$DEPLOY_PATH"

if [[ ! -f .env ]]; then
  echo "ERROR: Missing $DEPLOY_PATH/.env" >&2
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

docker volume inspect apigw-storage >/dev/null 2>&1 || docker volume create apigw-storage
docker volume inspect apigw-bootstrap-cache >/dev/null 2>&1 || docker volume create apigw-bootstrap-cache

if [[ "$IMAGE" == "durpalla-apigw-app:local" ]]; then
  docker build -t "$IMAGE" "$DEPLOY_PATH"
else
  docker pull "$IMAGE"
  docker tag "$IMAGE" durpalla-apigw-app:local
fi

for c in apigw-public-tmp durpalla-apigw-app; do
  docker rm -f "$c" >/dev/null 2>&1 || true
done

docker run -d --name durpalla-apigw-app --restart unless-stopped \
  -p "${APIGW_FPM_PORT}:9000" \
  --add-host=host.docker.internal:host-gateway \
  --env-file "$DEPLOY_PATH/.env" \
  -v apigw-storage:/var/www/html/storage \
  -v apigw-bootstrap-cache:/var/www/html/bootstrap/cache \
  durpalla-apigw-app:local

docker create --name apigw-public-tmp durpalla-apigw-app:local
rm -rf "$DEPLOY_PATH/public"
docker cp apigw-public-tmp:/var/www/html/public "$DEPLOY_PATH/public"
docker rm -f apigw-public-tmp

docker exec durpalla-apigw-app php artisan config:clear
docker exec durpalla-apigw-app php artisan route:clear
docker exec durpalla-apigw-app php artisan view:clear
docker exec durpalla-apigw-app php artisan config:cache
docker exec durpalla-apigw-app php artisan route:cache
docker exec durpalla-apigw-app php artisan view:cache
docker exec durpalla-apigw-app php artisan cache:clear || true

docker image prune -f >/dev/null 2>&1 || true

echo "Deployed durpalla-apigw on 127.0.0.1:${APIGW_FPM_PORT} -> ${DEPLOY_PATH}"
