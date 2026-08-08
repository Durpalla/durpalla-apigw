#!/usr/bin/env bash
# Post-deploy host gate — fail unless all four backends share one image and pass /up + trip.
set -euo pipefail

TRIP_ID="${TRIP_ID:-10}"
EXPECTED_IMAGE_ID="${EXPECTED_IMAGE_ID:-}"

echo "Verifying durpalla-apigw-1..4 on $(hostname)..."

fail=0
ids=()
for idx in 1 2 3 4; do
  port=$((8000 + idx))
  name="durpalla-apigw-${idx}"

  if ! docker inspect "$name" >/dev/null 2>&1; then
    echo "FAIL: ${name} missing"
    fail=1
    continue
  fi

  img="$(docker inspect -f '{{.Image}}' "$name")"
  ids+=("$img")
  echo "  ${name}: image=${img}"

  if [[ -n "$EXPECTED_IMAGE_ID" && "$img" != "$EXPECTED_IMAGE_ID" ]]; then
    echo "FAIL: ${name} image mismatch (expected ${EXPECTED_IMAGE_ID})"
    fail=1
  fi

  if ! curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; then
    echo "FAIL: ${name} /up on :${port}"
    fail=1
    continue
  fi

  trip_ok=0
  for attempt in 1 2 3; do
    code="$(curl -sS -o /tmp/ci-trip.json -w '%{http_code}' --max-time 25 \
      -H 'Accept: application/json' \
      "http://127.0.0.1:${port}/api/v1/trip/${TRIP_ID}" || echo 000)"
    if [[ "$code" == "200" ]] && grep -q '"success":true' /tmp/ci-trip.json 2>/dev/null; then
      trip_ok=1
      break
    fi
    sleep 2
  done
  if [[ "$trip_ok" -ne 1 ]]; then
    echo "FAIL: ${name} trip/${TRIP_ID} HTTP ${code}"
    head -c 200 /tmp/ci-trip.json 2>/dev/null || true
    echo
    fail=1
  else
    echo "  OK ${name} trip/${TRIP_ID}"
  fi
done

uniq_ids="$(printf '%s\n' "${ids[@]}" | sort -u | wc -l | tr -d ' ')"
if [[ "${#ids[@]}" -eq 4 && "$uniq_ids" -ne 1 ]]; then
  echo "FAIL: containers run mixed images:"
  printf '  %s\n' "${ids[@]}"
  fail=1
fi

# No extras on 8001-8004
for port in 8001 8002 8003 8004; do
  holders="$(docker ps --filter "publish=${port}" --format '{{.Names}}' || true)"
  count="$(printf '%s\n' "$holders" | grep -c . || true)"
  if [[ "$count" -ne 1 ]]; then
    echo "FAIL: port ${port} has ${count} publisher(s): ${holders:-none}"
    fail=1
  fi
done

if [[ "$fail" -ne 0 ]]; then
  docker ps -a --filter name=durpalla-apigw --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}' || true
  exit 1
fi

echo "Host verify OK — all four containers identical and healthy."
