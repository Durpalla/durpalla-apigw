#!/usr/bin/env bash
# Copy oauth-*.key from the main Durpalla app (or DURPALLA_PASSPORT_KEYS_DIR) into
# apigw-storage when the volume has no keys yet. Same RSA pair as the main app.
set -euo pipefail

VOLUME="${APIGW_STORAGE_VOLUME:-apigw-storage}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

volume_has_keys() {
  docker run --rm -v "${VOLUME}:/storage:ro" alpine:3.20 sh -c \
    'test -s /storage/oauth-private.key && test -s /storage/oauth-public.key'
}

find_passport_source() {
  if [[ -n "${DURPALLA_PASSPORT_KEYS_DIR:-}" ]]; then
    if [[ -f "${DURPALLA_PASSPORT_KEYS_DIR}/oauth-private.key" && -f "${DURPALLA_PASSPORT_KEYS_DIR}/oauth-public.key" ]]; then
      echo "${DURPALLA_PASSPORT_KEYS_DIR}"
      return 0
    fi
    echo "WARNING: DURPALLA_PASSPORT_KEYS_DIR=${DURPALLA_PASSPORT_KEYS_DIR} has no oauth-*.key" >&2
    return 1
  fi

  local dir
  for dir in \
    /var/www/html/durpalla/storage \
    /opt/durpalla/storage \
    "${DEPLOY_PATH:-}/storage"; do
    if [[ -f "${dir}/oauth-private.key" && -f "${dir}/oauth-public.key" ]]; then
      echo "${dir}"
      return 0
    fi
  done

  return 1
}

seed_from_source() {
  local src="$1"
  echo "Seeding Passport keys from ${src} into Docker volume ${VOLUME}..."
  docker run --rm \
    -v "${VOLUME}:/storage" \
    -v "${src}:/source:ro" \
    alpine:3.20 sh -c '
      set -e
      if test -s /storage/oauth-private.key && test -s /storage/oauth-public.key; then
        echo "Passport keys already on apigw-storage volume."
        exit 0
      fi
      cp /source/oauth-private.key /source/oauth-public.key /storage/
      chmod 660 /storage/oauth-private.key /storage/oauth-public.key
      echo "Copied oauth-private.key and oauth-public.key into apigw-storage."
    '
}

docker volume inspect "${VOLUME}" >/dev/null 2>&1 || docker volume create "${VOLUME}"

if volume_has_keys; then
  echo "Passport keys already present on ${VOLUME}."
  exit 0
fi

if src="$(find_passport_source)"; then
  seed_from_source "${src}"
else
  echo "No host Passport key directory found (optional: set DURPALLA_PASSPORT_KEYS_DIR)."
fi
