#!/usr/bin/env bash
# apigw + assets on port 80 via Docker host network (servers without host nginx, e.g. .94).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF="${ROOT}/docker/nginx/assets-and-apigw.host-network.conf"
MOUNT_ROOT="${MOUNT_ROOT:-/mnt/durpalla-assets}"
CONTAINER="${HOST_NGINX_CONTAINER:-assets-cdn}"

if [[ ! -f "$CONF" ]]; then
  echo "ERROR: Missing $CONF" >&2
  exit 1
fi

if [[ ! -d "${MOUNT_ROOT}/storage" ]]; then
  echo "ERROR: ${MOUNT_ROOT}/storage missing." >&2
  exit 1
fi

for port in 8001 8002 8003 8004; do
  if ! curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; then
    echo "WARN: apigw not responding on 127.0.0.1:${port}/up — start containers first."
  fi
done

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d --name "$CONTAINER" --restart unless-stopped --network host \
  -v "$CONF:/etc/nginx/conf.d/default.conf:ro" \
  -v "${MOUNT_ROOT}:${MOUNT_ROOT}:ro" \
  nginx:alpine

echo "Host-network nginx: assets.durpalla.com + apigw.durpalla.com on port 80"
curl -sI -H 'Host: apigw.durpalla.com' http://127.0.0.1/up | head -3 || true
