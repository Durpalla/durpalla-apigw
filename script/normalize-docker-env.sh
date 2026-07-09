#!/usr/bin/env bash
# Normalize .env host values for Docker containers on app servers.
set -euo pipefail

DEPLOY_PATH="${1:?DEPLOY_PATH required}"

ENV_FILE="${DEPLOY_PATH}/.env"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Prefer the bridge gateway IP (always resolvable). Fall back to host.docker.internal
# when the deploy script also maps it via --add-host.
DOCKER_HOST_GATEWAY="${DOCKER_HOST_GATEWAY:-$(
  bash "${SCRIPT_DIR}/docker-host-gateway.sh" 2>/dev/null || echo "host.docker.internal"
)}"

sed -i '/^DB_SOCKET=/d' "$ENV_FILE"

for var in DB_HOST MONGODB_HOST; do
  sed -i "s/^${var}=localhost$/${var}=${DOCKER_HOST_GATEWAY}/" "$ENV_FILE"
  sed -i "s/^${var}=127.0.0.1$/${var}=${DOCKER_HOST_GATEWAY}/" "$ENV_FILE"
  sed -i "s/^${var}=host.docker.internal$/${var}=${DOCKER_HOST_GATEWAY}/" "$ENV_FILE"
done

if grep -q '^REDIS_SENTINEL_ENABLED=true' "$ENV_FILE"; then
  # Sentinel: use cluster IPs, not 127.0.0.1 (that is the container loopback).
  S1="${REDIS_SENTINEL_1_HOST:-103.60.204.94}"
  S2="${REDIS_SENTINEL_2_HOST:-103.60.204.200}"
  S3="${REDIS_SENTINEL_3_HOST:-103.60.204.238}"

  for pair in \
    "REDIS_SENTINEL_1_HOST:${S1}" \
    "REDIS_SENTINEL_2_HOST:${S2}" \
    "REDIS_SENTINEL_3_HOST:${S3}"; do
    key="${pair%%:*}"
    val="${pair#*:}"
    if grep -q "^${key}=" "$ENV_FILE"; then
      sed -i "s/^${key}=.*/${key}=${val}/" "$ENV_FILE"
    else
      echo "${key}=${val}" >> "$ENV_FILE"
    fi
  done

  if ! grep -q '^REDIS_CLIENT=' "$ENV_FILE"; then
    echo 'REDIS_CLIENT=predis' >> "$ENV_FILE"
  else
    sed -i 's/^REDIS_CLIENT=.*/REDIS_CLIENT=predis/' "$ENV_FILE"
  fi
else
  sed -i "s/^REDIS_HOST=localhost$/REDIS_HOST=${DOCKER_HOST_GATEWAY}/" "$ENV_FILE"
  sed -i "s/^REDIS_HOST=127.0.0.1$/REDIS_HOST=${DOCKER_HOST_GATEWAY}/" "$ENV_FILE"
  sed -i "s/^REDIS_HOST=host.docker.internal$/REDIS_HOST=${DOCKER_HOST_GATEWAY}/" "$ENV_FILE"
  sed -i "s|^REDIS_URL=redis://127.0.0.1:|REDIS_URL=redis://${DOCKER_HOST_GATEWAY}:|" "$ENV_FILE"
  sed -i "s|^REDIS_URL=redis://localhost:|REDIS_URL=redis://${DOCKER_HOST_GATEWAY}:|" "$ENV_FILE"
  sed -i "s|^REDIS_URL=redis://host.docker.internal:|REDIS_URL=redis://${DOCKER_HOST_GATEWAY}:|" "$ENV_FILE"
fi

# OpenTelemetry collector on the Docker host (when enabled).
sed -i "s|http://host.docker.internal:|http://${DOCKER_HOST_GATEWAY}:|g" "$ENV_FILE"
sed -i "s|http://127.0.0.1:4318|http://${DOCKER_HOST_GATEWAY}:4318|g" "$ENV_FILE"
