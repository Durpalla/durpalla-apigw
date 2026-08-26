#!/usr/bin/env bash
# Build the apigw Docker image on the app server from a pinned git SHA, then roll containers.
# Invoked by .github/workflows/ci-deploy-server-build.yml (fire-and-forget via nohup).
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"
GIT_SHA="${GIT_SHA:?GIT_SHA is required}"
GIT_REPO="${GIT_REPO:?GIT_REPO is required}"
SRC_DIR="${SRC_DIR:-${DEPLOY_PATH}/src}"
SCRIPT_DIR="${DEPLOY_SCRIPT_DIR:-$(dirname "$0")}"
STATUS_DIR="${STATUS_DIR:-${DEPLOY_PATH}/ci-runner}"
LOCK_FILE="${STATUS_DIR}/deploy.lock"
STATUS_FILE="${STATUS_DIR}/status"
LOG_FILE="${STATUS_DIR}/deploy.log"

mkdir -p "$STATUS_DIR"

write_status() {
  local state="$1"
  local msg="${2:-}"
  {
    echo "state=${state}"
    echo "sha=${GIT_SHA}"
    echo "started_at=${STARTED_AT:-}"
    echo "updated_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "hostname=$(hostname)"
    [[ -n "$msg" ]] && echo "message=${msg}"
  } >"$STATUS_FILE"
}

STARTED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
write_status running "deploy started"

NOTIFY_ARMED=0
notify_email() {
  local code="${1:-1}"
  local state msg
  if [[ "$code" -eq 0 ]]; then
    state=ok
    msg="Deploy succeeded"
  else
    state=failed
    msg="Deploy failed (exit ${code})"
  fi
  # Prefer message from status file when present.
  if [[ -f "$STATUS_FILE" ]] && grep -q '^message=' "$STATUS_FILE" 2>/dev/null; then
    msg="$(grep '^message=' "$STATUS_FILE" | head -1 | cut -d= -f2-)"
  fi
  if [[ -x "${SCRIPT_DIR}/ci-notify-email.sh" || -f "${SCRIPT_DIR}/ci-notify-email.sh" ]]; then
    echo "==== email notify (${state}) ===="
    bash "${SCRIPT_DIR}/ci-notify-email.sh" "$state" "$msg" \
      && echo "==== email notify done ====" \
      || echo "==== email notify FAILED (see above) ===="
  else
    echo "WARN: ci-notify-email.sh missing at ${SCRIPT_DIR} — no email sent."
  fi
}

cleanup_git_key() {
  if [[ -n "${GIT_SSH_KEY_FILE:-}" && -f "${GIT_SSH_KEY_FILE}" ]]; then
    rm -f "${GIT_SSH_KEY_FILE}"
  fi
}

on_exit() {
  local code=$?
  cleanup_git_key
  if [[ "${NOTIFY_ARMED:-0}" == "1" ]]; then
    notify_email "$code"
  fi
}
trap on_exit EXIT

# Serialize deploys on this host (background jobs from rapid pushes).
exec 9>"$LOCK_FILE"
if ! flock -w 7200 9; then
  write_status failed "could not acquire deploy lock"
  echo "ERROR: timed out waiting for deploy lock ${LOCK_FILE}"
  NOTIFY_ARMED=1
  exit 1
fi
NOTIFY_ARMED=1

if [[ ! -d "$DEPLOY_PATH" ]]; then
  write_status failed "DEPLOY_PATH missing"
  echo "ERROR: $DEPLOY_PATH does not exist."
  exit 1
fi

if [[ ! -w "$DEPLOY_PATH" ]]; then
  write_status failed "DEPLOY_PATH not writable"
  echo "ERROR: $DEPLOY_PATH is not writable by $(whoami)"
  exit 1
fi

if [[ ! -f "$DEPLOY_PATH/.env" ]]; then
  write_status failed ".env missing"
  echo "Missing $DEPLOY_PATH/.env — create it before first deploy"
  exit 1
fi

GIT_SSH_KEY_FILE=""

ensure_github_known_hosts() {
  mkdir -p "${HOME}/.ssh"
  chmod 700 "${HOME}/.ssh"
  local kh="${HOME}/.ssh/known_hosts"
  touch "$kh"
  chmod 600 "$kh"
  if ! grep -qE '^github\.com |^\[github\.com\]' "$kh" 2>/dev/null; then
    echo "Adding github.com to ${kh}..."
    ssh-keyscan -t rsa,ecdsa,ed25519 github.com >>"$kh" 2>/dev/null || true
  fi
}

if [[ -n "${GIT_SSH_KEY_B64:-}" ]]; then
  ensure_github_known_hosts
  GIT_SSH_KEY_FILE="$(mktemp)"
  chmod 600 "$GIT_SSH_KEY_FILE"
  printf '%s' "$GIT_SSH_KEY_B64" | base64 -d >"$GIT_SSH_KEY_FILE" 2>/dev/null \
    || printf '%s' "$GIT_SSH_KEY_B64" | base64 -D >"$GIT_SSH_KEY_FILE"
  export GIT_SSH_COMMAND="ssh -i ${GIT_SSH_KEY_FILE} -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=${HOME}/.ssh/known_hosts"
elif [[ "${GIT_REPO}" == git@* || "${GIT_REPO}" == ssh://* ]]; then
  ensure_github_known_hosts
  export GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=yes -o UserKnownHostsFile=${HOME}/.ssh/known_hosts"
fi

if [[ -n "${GIT_TOKEN:-}" ]]; then
  if [[ "$GIT_REPO" == https://* ]]; then
    GIT_REPO_AUTH="https://x-access-token:${GIT_TOKEN}@${GIT_REPO#https://}"
  elif [[ "$GIT_REPO" == git@github.com:* ]]; then
    path="${GIT_REPO#git@github.com:}"
    path="${path%.git}.git"
    GIT_REPO_AUTH="https://x-access-token:${GIT_TOKEN}@github.com/${path}"
    echo "Using HTTPS+token clone (GIT_TOKEN set)."
  else
    GIT_REPO_AUTH="$GIT_REPO"
  fi
else
  GIT_REPO_AUTH="$GIT_REPO"
fi

SAFE_REPO_LOG="$GIT_REPO"
[[ -n "${GIT_TOKEN:-}" ]] && SAFE_REPO_LOG="https://github.com/… (token)"
echo "Syncing ${SRC_DIR} → ${GIT_SHA} from ${SAFE_REPO_LOG}"
mkdir -p "$(dirname "$SRC_DIR")"

if [[ -d "$SRC_DIR" && ! -d "$SRC_DIR/.git" ]]; then
  echo "Removing incomplete checkout at ${SRC_DIR}..."
  rm -rf "$SRC_DIR"
fi

if [[ ! -d "$SRC_DIR/.git" ]]; then
  echo "Cloning into ${SRC_DIR}..."
  git clone --depth 50 "$GIT_REPO_AUTH" "$SRC_DIR"
fi

cd "$SRC_DIR"

git remote set-url origin "$GIT_REPO_AUTH" 2>/dev/null || git remote add origin "$GIT_REPO_AUTH"

if ! git cat-file -e "${GIT_SHA}^{commit}" 2>/dev/null; then
  echo "Fetching ${GIT_SHA}..."
  git fetch --depth 50 origin "$GIT_SHA" 2>/dev/null \
    || git fetch --depth 50 origin "+refs/heads/*:refs/remotes/origin/*" \
    || git fetch --unshallow origin 2>/dev/null \
    || git fetch origin
fi

if ! git cat-file -e "${GIT_SHA}^{commit}" 2>/dev/null; then
  write_status failed "commit not found"
  echo "ERROR: commit ${GIT_SHA} not found in ${SRC_DIR} after fetch."
  exit 1
fi

git checkout --force --detach "$GIT_SHA"
git clean -fd

if [[ ! -f "$SRC_DIR/Dockerfile" ]]; then
  write_status failed "Dockerfile missing"
  echo "ERROR: Dockerfile missing at ${SRC_DIR}/Dockerfile"
  exit 1
fi

IMAGE_TAG="durpalla-apigw-app:${GIT_SHA:0:12}"
IMAGE_LOCAL="durpalla-apigw-app:local"

echo "Building ${IMAGE_TAG} (also tagging ${IMAGE_LOCAL}) from ${SRC_DIR}..."
DOCKER_BUILDKIT=1 docker build \
  --progress=plain \
  -t "$IMAGE_TAG" \
  -t "$IMAGE_LOCAL" \
  -f "$SRC_DIR/Dockerfile" \
  "$SRC_DIR"

EXPECTED_IMAGE_ID="$(docker image inspect -f '{{.Id}}' "$IMAGE_TAG")"
if [[ -z "$EXPECTED_IMAGE_ID" ]]; then
  write_status failed "image id missing after build"
  echo "ERROR: could not resolve image id for ${IMAGE_TAG}"
  exit 1
fi
echo "Built image id: ${EXPECTED_IMAGE_ID}"

export DEPLOY_PATH
export IMAGE="$IMAGE_TAG"
export LOCAL_IMAGE_BUILD=1
export DEPLOY_SCRIPT_DIR="$SCRIPT_DIR"
export GHCR_USER="${GHCR_USER:-local}"
export GHCR_TOKEN_B64="${GHCR_TOKEN_B64:-}"

if ! bash "${SCRIPT_DIR}/ci-deploy-remote.sh"; then
  write_status failed "container roll failed"
  exit 1
fi

write_status ok "deployed ${IMAGE_TAG}"
echo "Server-build deploy complete: ${IMAGE_TAG} (${EXPECTED_IMAGE_ID}) on $(hostname)"
echo "Log: ${LOG_FILE}"
