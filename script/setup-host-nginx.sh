#!/usr/bin/env bash
# Install host nginx site for durpalla-apigw (same pattern as billman / larabill).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF_SRC="${ROOT}/docker/nginx/host.conf.example"
SITE_NAME="${NGINX_SITE_NAME:-apigw.durpalla.com}"
SITES_AVAILABLE="${NGINX_SITES_AVAILABLE:-/etc/nginx/sites-available}"
SITES_ENABLED="${NGINX_SITES_ENABLED:-/etc/nginx/sites-enabled}"
DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"

if [[ ! -f "$CONF_SRC" ]]; then
  echo "ERROR: Missing $CONF_SRC" >&2
  echo "Run this script from the cloned repo (e.g. /var/www/html/durpalla-apigw or /opt/durpalla-apigw)." >&2
  exit 1
fi

if [[ ! -d "$DEPLOY_PATH/public" ]]; then
  echo "WARN: $DEPLOY_PATH/public not found yet."
  echo "Deploy the container first (./script/docker-deploy.sh) so public/ is extracted."
fi

TMP="$(mktemp)"
sed "s|/opt/durpalla-apigw|${DEPLOY_PATH}|g" "$CONF_SRC" > "$TMP"

echo "Installing nginx site: $SITE_NAME"
echo "  source:  $CONF_SRC"
echo "  target:  $SITES_AVAILABLE/$SITE_NAME"
echo "  docroot: $DEPLOY_PATH/public"

sudo cp "$TMP" "$SITES_AVAILABLE/$SITE_NAME"
rm -f "$TMP"

if [[ -L "$SITES_ENABLED/$SITE_NAME" ]] || [[ -e "$SITES_ENABLED/$SITE_NAME" ]]; then
  sudo rm -f "$SITES_ENABLED/$SITE_NAME"
fi

sudo ln -s "$SITES_AVAILABLE/$SITE_NAME" "$SITES_ENABLED/$SITE_NAME"

sudo nginx -t
sudo systemctl reload nginx

echo "Done. Site enabled: $SITE_NAME -> fastcgi 127.0.0.1:${APIGW_FPM_PORT:-9005}"
