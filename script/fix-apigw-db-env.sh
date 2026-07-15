#!/usr/bin/env bash
# Fix apigw .env DB_HOST and refresh Laravel config in all apigw containers.
set -euo pipefail

ENV_FILE="${1:-/opt/durpalla-apigw/.env}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_PATH="$(dirname "$ENV_FILE")"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: $ENV_FILE not found" >&2
  exit 1
fi

cp "$ENV_FILE" "${ENV_FILE}.bak.db-fix-$(date +%s)"

bash "${SCRIPT_DIR}/normalize-docker-env.sh" "$DEPLOY_PATH"

echo "--- DB settings ---"
grep -E '^(DB_HOST|DB_PORT|DB_WRITE_PORT|DB_READ_PORT|DB_ROUTER_HOST)=' "$ENV_FILE"

refresh_container() {
  local c="$1"
  if ! docker inspect "$c" >/dev/null 2>&1; then
    echo "SKIP  $c not running"
    return 0
  fi
  echo "Refreshing config in $c..."
  docker exec -T "$c" php artisan config:clear --no-interaction
  docker exec -T "$c" php -d memory_limit=512M artisan config:cache --no-interaction
}

for c in durpalla-apigw-1 durpalla-apigw-2 durpalla-apigw-3 durpalla-apigw-4; do
  refresh_container "$c"
done

echo "Verifying MySQL from durpalla-apigw-1..."
if docker exec -T durpalla-apigw-1 php artisan db:show --no-interaction; then
  echo "Done."
else
  echo "ERROR: still cannot connect. Check Router on 6446:" >&2
  echo "  for h in 103.60.204.94 103.60.204.200 103.60.204.238; do nc -z -w2 \$h 6446 && echo OK \$h; done" >&2
  exit 1
fi
