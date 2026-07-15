#!/usr/bin/env bash
# ONE-TIME bootstrap: install host nginx for apigw.durpalla.com (proxy to Docker 8001–8004).
# CI deploy does NOT run this — only script/ci-deploy-remote.sh (container restart).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
UPSTREAM_SRC="${ROOT}/docker/nginx/upstream.conf"
CF_SRC="${ROOT}/docker/nginx/cloudflare-real-ip.conf"
SITE_SRC="${ROOT}/docker/nginx/apigw.durpalla.com.conf"
SITE_NAME="${NGINX_SITE_NAME:-apigw.durpalla.com}"
SITES_AVAILABLE="${NGINX_SITES_AVAILABLE:-/etc/nginx/sites-available}"
SITES_ENABLED="${NGINX_SITES_ENABLED:-/etc/nginx/sites-enabled}"
CONF_D="${NGINX_CONF_D:-/etc/nginx/conf.d}"

if [[ ! -f "$UPSTREAM_SRC" ]] || [[ ! -f "$SITE_SRC" ]] || [[ ! -f "$CF_SRC" ]]; then
  echo "ERROR: Missing nginx configs under $ROOT/docker/nginx/" >&2
  exit 1
fi

echo "Installing Cloudflare real-ip map -> ${CONF_D}/cloudflare-real-ip.conf"
sudo cp "$CF_SRC" "${CONF_D}/cloudflare-real-ip.conf"

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
echo ""
echo "Cloudflare Flexible SSL: point DNS (proxied) to this server — port 80 is enough."
echo "Cloudflare Full (strict): run once: sudo bash script/setup-apigw-cloudflare-ssl.sh"
