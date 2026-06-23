#!/usr/bin/env bash
# Install host nginx site for apigw.durpalla.com (proxy to Docker 8001–8004).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
UPSTREAM_SRC="${ROOT}/docker/nginx/upstream.conf"
SITE_SRC="${ROOT}/docker/nginx/apigw.durpalla.com.conf"
SITE_NAME="${NGINX_SITE_NAME:-apigw.durpalla.com}"
SITES_AVAILABLE="${NGINX_SITES_AVAILABLE:-/etc/nginx/sites-available}"
SITES_ENABLED="${NGINX_SITES_ENABLED:-/etc/nginx/sites-enabled}"
CONF_D="${NGINX_CONF_D:-/etc/nginx/conf.d}"

if [[ ! -f "$UPSTREAM_SRC" ]] || [[ ! -f "$SITE_SRC" ]]; then
  echo "ERROR: Missing nginx configs under $ROOT/docker/nginx/" >&2
  exit 1
fi

echo "Installing upstream -> ${CONF_D}/apigw-upstream.conf"
sudo cp "$UPSTREAM_SRC" "${CONF_D}/apigw-upstream.conf"

echo "Installing site -> ${SITES_AVAILABLE}/${SITE_NAME}"
sudo cp "$SITE_SRC" "${SITES_AVAILABLE}/${SITE_NAME}"

if [[ -L "${SITES_ENABLED}/${SITE_NAME}" ]] || [[ -e "${SITES_ENABLED}/${SITE_NAME}" ]]; then
  sudo rm -f "${SITES_ENABLED}/${SITE_NAME}"
fi
sudo ln -s "${SITES_AVAILABLE}/${SITE_NAME}" "${SITES_ENABLED}/${SITE_NAME}"

# Disable default site if present (serves "Welcome to nginx!")
if [[ -L "${SITES_ENABLED}/default" ]] || [[ -e "${SITES_ENABLED}/default" ]]; then
  echo "Disabling default nginx site..."
  sudo rm -f "${SITES_ENABLED}/default"
fi

echo "Checking containers respond on 8001–8004..."
for port in 8001 8002 8003 8004; do
  if curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; then
    echo "  OK 127.0.0.1:${port}/up"
  else
    echo "  WARN 127.0.0.1:${port}/up not responding — start containers first:"
    echo "       cd /opt/durpalla-apigw && docker compose -f docker-compose.prod.yml up -d"
  fi
done

sudo nginx -t
sudo systemctl reload nginx

echo ""
echo "Done. Test:"
echo "  curl -H 'Host: apigw.durpalla.com' http://127.0.0.1/up"
echo "  curl http://apigw.durpalla.com/up"
