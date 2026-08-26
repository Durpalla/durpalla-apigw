#!/usr/bin/env bash
# Send deploy status email using MAIL_* from the apigw .env.
# Usage: ci-notify-email.sh <ok|failed> [message]
set -euo pipefail

STATE="${1:?state required (ok|failed)}"
MESSAGE="${2:-}"
DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"
ENV_FILE="${ENV_FILE:-${DEPLOY_PATH}/.env}"
STATUS_DIR="${STATUS_DIR:-${DEPLOY_PATH}/ci-runner}"
LOG_FILE="${STATUS_DIR}/deploy.log"
STATUS_FILE="${STATUS_DIR}/status"
GIT_SHA="${GIT_SHA:-unknown}"
NOTIFY_TO="${DEPLOY_NOTIFY_EMAIL:-jewelrana.dev@gmail.com}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "WARN: php not found on host — skipping deploy email notify."
  exit 0
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "WARN: ${ENV_FILE} missing — skipping deploy email notify."
  exit 0
fi

HOST="$(hostname -f 2>/dev/null || hostname)"
TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
case "$STATE" in
  ok|success) SUBJECT="[apigw OK] deploy ${GIT_SHA:0:12} on ${HOST}" ;;
  *) SUBJECT="[apigw FAIL] deploy ${GIT_SHA:0:12} on ${HOST}" ;;
esac

BODY_FILE="$(mktemp)"
trap 'rm -f "$BODY_FILE"' EXIT

{
  echo "Durpalla API Gateway — server-side CI deploy"
  echo "============================================"
  echo "State:     ${STATE}"
  echo "Host:      ${HOST}"
  echo "SHA:       ${GIT_SHA}"
  echo "Time(UTC): ${TS}"
  echo "Path:      ${DEPLOY_PATH}"
  [[ -n "$MESSAGE" ]] && echo "Message:   ${MESSAGE}"
  echo
  if [[ -f "$STATUS_FILE" ]]; then
    echo "--- status file ---"
    cat "$STATUS_FILE"
    echo
  fi
  if [[ -f "$LOG_FILE" ]]; then
    echo "--- last 80 log lines (${LOG_FILE}) ---"
    tail -n 80 "$LOG_FILE" || true
  fi
} >"$BODY_FILE"

"$PHP_BIN" "${SCRIPT_DIR}/ci-notify-email.php" \
  --env="$ENV_FILE" \
  --to="$NOTIFY_TO" \
  --subject="$SUBJECT" \
  --body-file="$BODY_FILE" \
  || echo "WARN: deploy email notify failed (non-fatal)."
