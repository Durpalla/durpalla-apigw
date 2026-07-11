#!/usr/bin/env bash
# One-time: enable HTTPS for apigw.durpalla.com using a Cloudflare Origin Certificate.
# Does NOT run during CI deploy — run manually once per server with sudo.
#
# Prerequisites:
#   1. Cloudflare Dashboard → SSL/TLS → Origin Server → Create Certificate
#   2. Save certificate + private key on this server:
#        /etc/ssl/cloudflare/apigw.durpalla.com.pem
#        /etc/ssl/cloudflare/apigw.durpalla.com.key
#   3. Cloudflare SSL mode: Full (strict)
#   4. Host nginx already proxying HTTP (setup-host-nginx.sh)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSL_SRC="${ROOT}/docker/nginx/apigw.durpalla.com.ssl.conf"
SITE_NAME="${NGINX_SITE_NAME:-apigw.durpalla.com}"
SITES_AVAILABLE="${NGINX_SITES_AVAILABLE:-/etc/nginx/sites-available}"
SITES_ENABLED="${NGINX_SITES_ENABLED:-/etc/nginx/sites-enabled}"

CERT_DIR="${DURPALLA_CERT_DIR:-/opt/durpalla}"
CERT_FILE="${CERT_DIR}/durpalla-cert.pem"
KEY_FILE="${CERT_DIR}/durpalla-key.pem"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run with sudo: sudo bash $0" >&2
  exit 1
fi

if [[ ! -f "$SSL_SRC" ]]; then
  echo "ERROR: Missing $SSL_SRC" >&2
  exit 1
fi

if [[ ! -f "$CERT_FILE" ]] || [[ ! -f "$KEY_FILE" ]]; then
  echo "ERROR: Shared Cloudflare cert files not found." >&2
  echo "" >&2
  echo "Install first:" >&2
  echo "  sudo bash script/install-durpalla-cert.sh /path/to/cert.pem /path/to/key.pem" >&2
  echo "Or sync from dev: bash script/sync-durpalla-cert-to-servers.sh" >&2
  exit 1
fi

if ! openssl x509 -in "$CERT_FILE" -noout >/dev/null 2>&1; then
  echo "ERROR: $CERT_FILE is not a valid PEM certificate." >&2
  exit 1
fi

if ! openssl pkey -in "$KEY_FILE" -check -noout >/dev/null 2>&1; then
  echo "ERROR: $KEY_FILE is not a valid PEM private key." >&2
  exit 1
fi

echo "Installing HTTPS site -> ${SITES_AVAILABLE}/${SITE_NAME}-ssl"
cp "$SSL_SRC" "${SITES_AVAILABLE}/${SITE_NAME}-ssl"

if [[ -L "${SITES_ENABLED}/${SITE_NAME}-ssl" ]] || [[ -e "${SITES_ENABLED}/${SITE_NAME}-ssl" ]]; then
  rm -f "${SITES_ENABLED}/${SITE_NAME}-ssl"
fi
ln -s "${SITES_AVAILABLE}/${SITE_NAME}-ssl" "${SITES_ENABLED}/${SITE_NAME}-ssl"

nginx -t
systemctl reload nginx

echo ""
echo "HTTPS enabled for apigw.durpalla.com (Cloudflare Origin Certificate)."
echo "Set Cloudflare SSL/TLS mode to Full (strict)."
echo "Test: curl -sI https://apigw.durpalla.com/up"
