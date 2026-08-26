#!/usr/bin/env bash
# Send deploy status email using MAIL_* from the apigw .env.
# Prefers Laravel inside a running apigw container (host often has no php-cli).
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
NOTIFY_CONTAINER="${NOTIFY_CONTAINER:-durpalla-apigw-1}"

record_email() {
  local result="$1"
  echo "email_notify=${result}"
  if [[ -f "$STATUS_FILE" ]]; then
    grep -v '^email_notify=' "$STATUS_FILE" >"${STATUS_FILE}.tmp" 2>/dev/null || true
    echo "email_notify=${result}" >>"${STATUS_FILE}.tmp"
    mv "${STATUS_FILE}.tmp" "$STATUS_FILE"
  fi
}

if [[ ! -f "$ENV_FILE" ]]; then
  echo "WARN: ${ENV_FILE} missing — skipping deploy email notify."
  record_email "skipped:no_env"
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

mail_via_laravel_container() {
  local name="$1"
  if ! command -v docker >/dev/null 2>&1; then
    return 1
  fi
  if ! docker inspect -f '{{.State.Running}}' "$name" 2>/dev/null | grep -qx true; then
    return 1
  fi

  echo "Sending email via Laravel in container ${name} → ${NOTIFY_TO}"
  # Body via stdin; subject/to via env (avoid shell-quoting the body).
  if ! docker exec -i \
    -e NOTIFY_TO="$NOTIFY_TO" \
    -e NOTIFY_SUBJECT="$SUBJECT" \
    "$name" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$to = getenv("NOTIFY_TO") ?: "";
$subject = getenv("NOTIFY_SUBJECT") ?: "apigw deploy";
$body = stream_get_contents(STDIN);
if ($to === "" || $body === false || $body === "") {
    fwrite(STDERR, "missing to/body\n");
    exit(2);
}
$mailer = (string) config("mail.default");
if (in_array($mailer, ["log", "array"], true)) {
    fwrite(STDERR, "MAIL_MAILER={$mailer} — configure SMTP in .env (mail will not leave the server)\n");
    exit(3);
}
try {
    Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($to, $subject) {
        $message->to($to)->subject($subject);
    });
    echo "Laravel Mail::raw OK (mailer={$mailer})\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Mail failed: ".$e->getMessage()."\n");
    exit(1);
}
' <"$BODY_FILE"; then
    return 1
  fi
  return 0
}

mail_via_host_php() {
  if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    return 1
  fi
  if [[ ! -f "${SCRIPT_DIR}/ci-notify-email.php" ]]; then
    return 1
  fi
  echo "Sending email via host php SMTP helper → ${NOTIFY_TO}"
  "$PHP_BIN" "${SCRIPT_DIR}/ci-notify-email.php" \
    --env="$ENV_FILE" \
    --to="$NOTIFY_TO" \
    --subject="$SUBJECT" \
    --body-file="$BODY_FILE"
}

mail_via_php_docker() {
  if ! command -v docker >/dev/null 2>&1; then
    return 1
  fi
  if [[ ! -f "${SCRIPT_DIR}/ci-notify-email.php" ]]; then
    return 1
  fi
  echo "Sending email via php:8.4-cli container → ${NOTIFY_TO}"
  docker run --rm --network host \
    -v "${ENV_FILE}:/deploy.env:ro" \
    -v "${BODY_FILE}:/body.txt:ro" \
    -v "${SCRIPT_DIR}/ci-notify-email.php:/notify.php:ro" \
    php:8.4-cli \
    php /notify.php \
      --env=/deploy.env \
      --to="$NOTIFY_TO" \
      --subject="$SUBJECT" \
      --body-file=/body.txt
}

# 1) Running apigw app (uses same MAIL_* as production)
# 2) Host php CLI helper
# 3) Ephemeral php:cli container
if mail_via_laravel_container "$NOTIFY_CONTAINER"; then
  record_email "sent:laravel:${NOTIFY_CONTAINER}"
  exit 0
fi

# Try any healthy apigw replica if primary is down mid-roll.
for c in durpalla-apigw-1 durpalla-apigw-2 durpalla-apigw-3 durpalla-apigw-4; do
  [[ "$c" == "$NOTIFY_CONTAINER" ]] && continue
  if mail_via_laravel_container "$c"; then
    record_email "sent:laravel:${c}"
    exit 0
  fi
done

if mail_via_host_php; then
  record_email "sent:host-php"
  exit 0
fi

if mail_via_php_docker; then
  record_email "sent:php-docker"
  exit 0
fi

echo "ERROR: all email notify methods failed (no running apigw container, no host php, php-docker failed)."
echo "Check MAIL_* in ${ENV_FILE} and that SMTP is reachable from the host/containers."
record_email "failed:all_methods"
exit 1
