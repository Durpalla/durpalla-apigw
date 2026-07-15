#!/usr/bin/env bash
# Run open-loop or closed-loop booking load tests with local k6 or Docker.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MODE="${1:-open}"
BASE_URL="${BASE_URL:-http://host.docker.internal:8000}"
TARGET_TPS="${TARGET_TPS:-5}"
DURATION="${DURATION:-2m}"
CLOSED_VUS="${CLOSED_VUS:-15}"

case "$MODE" in
  open|open-loop)
    SCRIPT="loadtests/open-loop.js"
    ;;
  closed|closed-loop)
    SCRIPT="loadtests/closed-loop.js"
    ;;
  *)
    echo "Usage: $0 {open|closed}"
    echo "Env: BASE_URL TARGET_TPS DURATION CLOSED_VUS"
    exit 1
    ;;
esac

COMMON_ENV=(
  -e "BASE_URL=${BASE_URL}"
  -e "TARGET_TPS=${TARGET_TPS}"
  -e "DURATION=${DURATION}"
  -e "CLOSED_VUS=${CLOSED_VUS}"
)

echo "==> Mode: ${MODE}"
echo "==> Script: ${SCRIPT}"
echo "==> BASE_URL=${BASE_URL} TARGET_TPS=${TARGET_TPS} DURATION=${DURATION}"

if command -v k6 >/dev/null 2>&1; then
  # Native k6: use localhost-friendly default if caller did not set BASE_URL
  if [[ -z "${BASE_URL_SET:-}" && "${BASE_URL}" == "http://host.docker.internal:8000" ]]; then
    BASE_URL="${NATIVE_BASE_URL:-http://127.0.0.1:8000}"
  fi
  exec k6 run \
    -e "BASE_URL=${BASE_URL}" \
    -e "TARGET_TPS=${TARGET_TPS}" \
    -e "DURATION=${DURATION}" \
    -e "CLOSED_VUS=${CLOSED_VUS}" \
    "${ROOT}/${SCRIPT}"
fi

if command -v docker >/dev/null 2>&1; then
  echo "==> k6 not on PATH; using docker.io/grafana/k6"
  exec docker run --rm -i \
    --add-host=host.docker.internal:host-gateway \
    -v "${ROOT}:/work" \
    -w /work \
    "${COMMON_ENV[@]}" \
    grafana/k6 run "/work/${SCRIPT}"
fi

echo "Neither k6 nor docker found. Install k6: https://grafana.com/docs/k6/latest/set-up/install-k6/"
exit 1
