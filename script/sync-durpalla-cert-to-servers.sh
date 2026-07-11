#!/usr/bin/env bash
# Push /opt/durpalla/durpalla-cert.pem (+ key) to all app servers and apply nginx SSL.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSH_USER="${DEPLOY_USER:-durpalla}"
SSH_PORT="${DEPLOY_PORT:-22}"

SERVERS=(
  103.60.204.94
  103.60.204.200
  103.60.204.238
)

CERT_SRC="${DURPALLA_CERT_SRC:-${ROOT}/.local/durpalla-cert.pem}"
KEY_SRC="${DURPALLA_KEY_SRC:-${ROOT}/.local/durpalla-key.pem}"

if [[ ! -f "$CERT_SRC" ]]; then
  echo "ERROR: Certificate not found at $CERT_SRC" >&2
  echo "Set DURPALLA_CERT_SRC or place cert at .local/durpalla-cert.pem (gitignored)." >&2
  exit 1
fi

if [[ ! -f "$KEY_SRC" ]]; then
  echo "ERROR: Private key not found at $KEY_SRC" >&2
  echo "Save Cloudflare Origin private key to .local/durpalla-key.pem or set DURPALLA_KEY_SRC." >&2
  exit 1
fi

SSH_OPTS=(
  -o StrictHostKeyChecking=accept-new
  -o ConnectTimeout=20
  -p "$SSH_PORT"
)
SCP_OPTS=(
  -o StrictHostKeyChecking=accept-new
  -o ConnectTimeout=20
  -P "$SSH_PORT"
)

for host in "${SERVERS[@]}"; do
  echo "=== ${SSH_USER}@${host} ==="

  if ! ssh "${SSH_OPTS[@]}" "${SSH_USER}@${host}" 'mkdir -p /opt/durpalla 2>/dev/null; sudo -n mkdir -p /opt/durpalla && sudo chmod 755 /opt/durpalla' 2>/dev/null; then
    echo "WARN: Could not create /opt/durpalla on ${host} (sudo password required)."
    echo "      Copy cert/key to /tmp on server, then run:"
    echo "      sudo mkdir -p /opt/durpalla"
    echo "      sudo install -m 644 /tmp/durpalla-cert.pem /opt/durpalla/durpalla-cert.pem"
    echo "      sudo install -m 600 /tmp/durpalla-key.pem /opt/durpalla/durpalla-key.pem"
    scp "${SCP_OPTS[@]}" "$CERT_SRC" "$KEY_SRC" "${SSH_USER}@${host}:/tmp/" || true
    continue
  fi

  scp "${SCP_OPTS[@]}" "$CERT_SRC" "${SSH_USER}@${host}:/tmp/durpalla-cert.pem"
  scp "${SCP_OPTS[@]}" "$KEY_SRC" "${SSH_USER}@${host}:/tmp/durpalla-key.pem"

  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${host}" 'mkdir -p /tmp/apigw-ssl-deploy'

  scp "${SCP_OPTS[@]}" \
    "${ROOT}/script/install-durpalla-cert.sh" \
    "${ROOT}/script/setup-apigw-cloudflare-ssl.sh" \
    "${ROOT}/script/setup-host-nginx.sh" \
    "${ROOT}/script/setup-host-network-nginx.sh" \
    "${ROOT}/docker/nginx/apigw.durpalla.com.conf" \
    "${ROOT}/docker/nginx/apigw.durpalla.com.ssl.conf" \
    "${ROOT}/docker/nginx/cloudflare-real-ip.conf" \
    "${ROOT}/docker/nginx/upstream.conf" \
    "${ROOT}/docker/nginx/assets-and-apigw.host-network.conf" \
    "${ROOT}/docker/nginx/assets-and-apigw.host-network.ssl.conf" \
    "${SSH_USER}@${host}:/tmp/apigw-ssl-deploy/"

  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${host}" 'bash -s' <<'REMOTE'
set -euo pipefail
DEPLOY=/tmp/apigw-ssl-deploy
sudo DURPALLA_CERT_SRC=/tmp/durpalla-cert.pem DURPALLA_KEY_SRC=/tmp/durpalla-key.pem \
  bash "$DEPLOY/install-durpalla-cert.sh"

# Host nginx (.200, .238) — skip on servers where nginx is not the active edge proxy
if command -v nginx >/dev/null 2>&1 && [[ -d /etc/nginx/sites-available ]] && systemctl is-active nginx >/dev/null 2>&1; then
  sudo cp "$DEPLOY/cloudflare-real-ip.conf" /etc/nginx/conf.d/cloudflare-real-ip.conf
  sudo cp "$DEPLOY/upstream.conf" /etc/nginx/conf.d/apigw-upstream.conf
  sudo cp "$DEPLOY/apigw.durpalla.com.conf" /etc/nginx/sites-available/apigw.durpalla.com
  sudo ln -sf /etc/nginx/sites-available/apigw.durpalla.com /etc/nginx/sites-enabled/apigw.durpalla.com
  sudo cp "$DEPLOY/apigw.durpalla.com.ssl.conf" /etc/nginx/sites-available/apigw.durpalla.com-ssl
  sudo ln -sf /etc/nginx/sites-available/apigw.durpalla.com-ssl /etc/nginx/sites-enabled/apigw.durpalla.com-ssl
  sudo rm -f /etc/nginx/sites-enabled/default
  sudo nginx -t
  sudo systemctl reload nginx
  echo "Host nginx SSL applied."
fi

# Docker host-network nginx (.94, .238)
if [[ -d /mnt/durpalla-assets/storage ]]; then
  export APIGW_HOST_NETWORK_CONF="$DEPLOY/assets-and-apigw.host-network.conf"
  export APIGW_HOST_NETWORK_SSL_CONF="$DEPLOY/assets-and-apigw.host-network.ssl.conf"
  bash "$DEPLOY/setup-host-network-nginx.sh"
fi

rm -f /tmp/durpalla-cert.pem /tmp/durpalla-key.pem
rm -rf "$DEPLOY"
REMOTE

  echo "Done ${host}"
done

echo ""
echo "All servers updated. Set Cloudflare SSL mode to Full (strict)."
echo "Test: curl -sI https://apigw.durpalla.com/up"
