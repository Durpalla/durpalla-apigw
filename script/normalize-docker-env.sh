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

set_kv() {
  local key="$1" val="$2"
  if grep -q "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" >> "$ENV_FILE"
  fi
}

get_kv() {
  local key="$1" default="${2:-}"
  local line
  line="$(grep -E "^${key}=" "$ENV_FILE" | tail -1 | cut -d= -f2- || true)"
  line="${line%\"}"
  line="${line#\"}"
  line="${line%\'}"
  line="${line#\'}"
  if [[ -z "$line" ]]; then
    echo "$default"
  else
    echo "$line"
  fi
}

port_open() {
  local host="$1" port="$2"
  if command -v nc >/dev/null 2>&1; then
    nc -z -w2 "$host" "$port" 2>/dev/null
    return $?
  fi
  timeout 2 bash -c "echo >/dev/tcp/${host}/${port}" 2>/dev/null
}

resolve_mysql_router_host() {
  local db_port="$1"
  local explicit_router current_host host

  explicit_router="$(get_kv DB_ROUTER_HOST "")"
  if [[ -n "$explicit_router" ]]; then
    echo "$explicit_router"
    return 0
  fi

  current_host="$(get_kv DB_HOST "")"
  if [[ -n "$current_host" \
        && "$current_host" != "127.0.0.1" \
        && "$current_host" != "localhost" \
        && "$current_host" != "host.docker.internal" \
        && "$current_host" != "$DOCKER_HOST_GATEWAY" ]]; then
    if port_open "$current_host" "$db_port"; then
      echo "$current_host"
      return 0
    fi
  fi

  # Bridge-network containers reach host services via the docker gateway — only works
  # when MySQL Router listens on 0.0.0.0 (not 127.0.0.1 only).
  if port_open "$DOCKER_HOST_GATEWAY" "$db_port"; then
    echo "$DOCKER_HOST_GATEWAY"
    return 0
  fi

  if port_open 127.0.0.1 "$db_port" && ! port_open "$DOCKER_HOST_GATEWAY" "$db_port"; then
    echo "NOTE: MySQL Router listens on 127.0.0.1:${db_port} only — bridge containers cannot use ${DOCKER_HOST_GATEWAY}." >&2
    echo "      Scanning cluster hosts for a reachable Router..." >&2
  fi

  local candidates_raw
  candidates_raw="$(get_kv MYSQL_ROUTER_CANDIDATES "")"
  if [[ -z "$candidates_raw" ]]; then
    candidates_raw="103.60.204.94 103.60.204.200 103.60.204.238"
  fi

  for host in $candidates_raw; do
    [[ "$host" == "127.0.0.1" || "$host" == "localhost" ]] && continue
    if port_open "$host" "$db_port"; then
      echo "$host"
      return 0
    fi
  done

  return 1
}

sed -i '/^DB_SOCKET=/d' "$ENV_FILE"

# MySQL Router read/write primary is 6446; 6450/6447 are read-only and break API writes.
if grep -q '^DB_PORT=6450$' "$ENV_FILE"; then
  sed -i 's/^DB_PORT=6450$/DB_PORT=6446/' "$ENV_FILE"
fi
if ! grep -q '^DB_WRITE_PORT=' "$ENV_FILE"; then
  echo 'DB_WRITE_PORT=6446' >> "$ENV_FILE"
else
  sed -i 's/^DB_WRITE_PORT=.*/DB_WRITE_PORT=6446/' "$ENV_FILE"
fi
if ! grep -q '^DB_READ_PORT=' "$ENV_FILE"; then
  echo 'DB_READ_PORT=6447' >> "$ENV_FILE"
else
  sed -i 's/^DB_READ_PORT=.*/DB_READ_PORT=6447/' "$ENV_FILE"
fi

db_port="$(get_kv DB_PORT 6446)"
if db_host="$(resolve_mysql_router_host "$db_port")"; then
  if [[ "$(get_kv DB_HOST "")" != "$db_host" ]]; then
    echo "MySQL Router reachable at ${db_host}:${db_port} — setting DB_HOST=${db_host}"
  fi
  set_kv DB_HOST "$db_host"
else
  echo "ERROR: MySQL Router not reachable on port ${db_port} from this host." >&2
  echo "Bridge-network apigw containers cannot use 127.0.0.1 for DB_HOST." >&2
  echo "Set in ${ENV_FILE}:" >&2
  echo "  DB_ROUTER_HOST=<ip>   # IP where Router accepts connections on ${db_port}" >&2
  echo "Or bind Router to 0.0.0.0 so ${DOCKER_HOST_GATEWAY}:${db_port} works from containers." >&2
  grep -E '^(DB_HOST|DB_PORT|DB_ROUTER_HOST)=' "$ENV_FILE" || true
  exit 1
fi

for var in MONGODB_HOST; do
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
