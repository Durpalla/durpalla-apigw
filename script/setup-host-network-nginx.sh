#!/usr/bin/env bash
# apigw + assets via Docker host network (servers without host nginx, e.g. .94).
# HTTP always; HTTPS on 443 when /opt/durpalla/durpalla-*.pem exist.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF="${APIGW_HOST_NETWORK_CONF:-${ROOT}/docker/nginx/assets-and-apigw.host-network.conf}"
SSL_CONF="${APIGW_HOST_NETWORK_SSL_CONF:-${ROOT}/docker/nginx/assets-and-apigw.host-network.ssl.conf}"
MOUNT_ROOT="${MOUNT_ROOT:-/mnt/durpalla-assets}"
CONTAINER="${HOST_NGINX_CONTAINER:-assets-cdn}"
CERT_FILE="${DURPALLA_CERT_FILE:-/opt/durpalla/durpalla-cert.pem}"
KEY_FILE="${DURPALLA_KEY_FILE:-/opt/durpalla/durpalla-key.pem}"

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

ssl_mounts=()
conf_mounts=( -v "$CONF:/etc/nginx/conf.d/default.conf:ro" )
if [[ -f "$CERT_FILE" && -f "$KEY_FILE" ]]; then
  echo "Mounting Cloudflare origin cert for HTTPS on 443"
  ssl_mounts=(
    -v "${CERT_FILE}:/etc/nginx/ssl/durpalla-cert.pem:ro"
    -v "${KEY_FILE}:/etc/nginx/ssl/durpalla-key.pem:ro"
  )
  conf_mounts+=( -v "$SSL_CONF:/etc/nginx/conf.d/apigw-ssl.conf:ro" )
else
  echo "WARN: ${CERT_FILE} or ${KEY_FILE} missing — container will serve HTTP only on port 80"
  echo "      Install with: sudo bash script/install-durpalla-cert.sh cert.pem key.pem"
fi

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d --name "$CONTAINER" --restart unless-stopped --network host \
  "${conf_mounts[@]}" \
  -v "${MOUNT_ROOT}:${MOUNT_ROOT}:ro" \
  "${ssl_mounts[@]}" \
  nginx:alpine

echo "Host-network nginx: assets.durpalla.com + apigw.durpalla.com"
curl -sI -H 'Host: apigw.durpalla.com' http://127.0.0.1/up | head -3 || true
if [[ ${#ssl_mounts[@]} -gt 0 ]]; then
  curl -skI -H 'Host: apigw.durpalla.com' https://127.0.0.1/up | head -3 || true
fi
