#!/usr/bin/env bash
# Install or update shared Cloudflare Origin Certificate under /opt/durpalla/.
# Run once per server (or from dev machine via script/sync-durpalla-cert-to-servers.sh).
#
# Files (wildcard *.durpalla.com — shared by apigw, assets, etc.):
#   /opt/durpalla/durpalla-cert.pem
#   /opt/durpalla/durpalla-key.pem
set -euo pipefail

CERT_DIR="${DURPALLA_CERT_DIR:-/opt/durpalla}"
CERT_FILE="${CERT_DIR}/durpalla-cert.pem"
KEY_FILE="${CERT_DIR}/durpalla-key.pem"

install_file() {
  local dest="$1"
  local src="${2:-}"

  if [[ -n "$src" && -f "$src" ]]; then
    if [[ "${EUID}" -ne 0 ]]; then
      sudo install -m "$(basename "$dest" | grep -q key && echo 600 || echo 644)" "$src" "$dest"
    else
      install -m "$(basename "$dest" | grep -q key && echo 600 || echo 644)" "$src" "$dest"
    fi
    echo "Installed $dest from $src"
    return 0
  fi

  if [[ -f "$dest" ]]; then
    echo "Keeping existing $dest"
    return 0
  fi

  return 1
}

run_as_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    sudo "$@"
  else
    "$@"
  fi
}

run_as_root mkdir -p "$CERT_DIR"
run_as_root chown root:root "$CERT_DIR"
run_as_root chmod 755 "$CERT_DIR"

cert_src="${DURPALLA_CERT_SRC:-}"
key_src="${DURPALLA_KEY_SRC:-}"

if [[ -z "$cert_src" && -z "$key_src" ]]; then
  if [[ $# -ge 1 ]]; then cert_src="$1"; fi
  if [[ $# -ge 2 ]]; then key_src="$2"; fi
fi

missing=0
if ! install_file "$CERT_FILE" "$cert_src"; then
  echo "ERROR: Missing certificate at $CERT_FILE" >&2
  echo "Provide: DURPALLA_CERT_SRC=/path/to/cert.pem $0" >&2
  missing=1
fi

if ! install_file "$KEY_FILE" "$key_src"; then
  echo "ERROR: Missing private key at $KEY_FILE" >&2
  echo "Cloudflare shows the private key once when you create the Origin Certificate." >&2
  echo "Provide: DURPALLA_KEY_SRC=/path/to/key.pem $0" >&2
  missing=1
fi

if [[ "$missing" -ne 0 ]]; then
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

# Verify cert and key match.
cert_mod="$(openssl x509 -noout -modulus -in "$CERT_FILE" | openssl md5)"
key_mod="$(openssl rsa -noout -modulus -in "$KEY_FILE" 2>/dev/null | openssl md5)"
if [[ -z "$key_mod" || "$cert_mod" != "$key_mod" ]]; then
  echo "ERROR: Certificate and private key do not match." >&2
  exit 1
fi

run_as_root chmod 644 "$CERT_FILE"
run_as_root chmod 600 "$KEY_FILE"
run_as_root chown root:root "$CERT_FILE" "$KEY_FILE"

echo "Cloudflare origin cert OK:"
openssl x509 -in "$CERT_FILE" -noout -subject -dates
