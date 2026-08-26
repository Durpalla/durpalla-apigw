#!/usr/bin/env bash
# Build the apigw Docker image on the app server from a pinned git SHA, then roll containers.
# Invoked by .github/workflows/ci-deploy-server-build.yml over SSH — no GHCR, no GitHub build minutes.
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/opt/durpalla-apigw}"
GIT_SHA="${GIT_SHA:?GIT_SHA is required}"
GIT_REPO="${GIT_REPO:?GIT_REPO is required}" # e.g. git@github.com:Durpalla/durpalla-apigw.git
SRC_DIR="${SRC_DIR:-${DEPLOY_PATH}/src}"
SCRIPT_DIR="${DEPLOY_SCRIPT_DIR:-$(dirname "$0")}"

if [[ ! -d "$DEPLOY_PATH" ]]; then
  echo "ERROR: $DEPLOY_PATH does not exist."
  echo "Bootstrap once: sudo mkdir -p $DEPLOY_PATH && sudo chown -R \$(whoami):\$(whoami) $DEPLOY_PATH"
  exit 1
fi

if [[ ! -w "$DEPLOY_PATH" ]]; then
  echo "ERROR: $DEPLOY_PATH is not writable by $(whoami)"
  exit 1
fi

if [[ ! -f "$DEPLOY_PATH/.env" ]]; then
  echo "Missing $DEPLOY_PATH/.env — create it before first deploy"
  exit 1
fi

# Optional: write a short-lived deploy key for private git fetch (base64 PEM).
GIT_SSH_KEY_FILE=""
cleanup_git_key() {
  if [[ -n "${GIT_SSH_KEY_FILE}" && -f "${GIT_SSH_KEY_FILE}" ]]; then
    rm -f "${GIT_SSH_KEY_FILE}"
  fi
}
trap cleanup_git_key EXIT

if [[ -n "${GIT_SSH_KEY_B64:-}" ]]; then
  GIT_SSH_KEY_FILE="$(mktemp)"
  chmod 600 "$GIT_SSH_KEY_FILE"
  printf '%s' "$GIT_SSH_KEY_B64" | base64 -d >"$GIT_SSH_KEY_FILE" 2>/dev/null \
    || printf '%s' "$GIT_SSH_KEY_B64" | base64 -D >"$GIT_SSH_KEY_FILE"
  export GIT_SSH_COMMAND="ssh -i ${GIT_SSH_KEY_FILE} -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
fi

# HTTPS clone with a PAT (x-access-token) when GIT_TOKEN is set and repo is https://...
if [[ -n "${GIT_TOKEN:-}" && "$GIT_REPO" == https://* ]]; then
  # Insert token without echoing it in process lists longer than needed.
  GIT_REPO_AUTH="https://x-access-token:${GIT_TOKEN}@${GIT_REPO#https://}"
else
  GIT_REPO_AUTH="$GIT_REPO"
fi

echo "Syncing ${SRC_DIR} → ${GIT_SHA}"
mkdir -p "$(dirname "$SRC_DIR")"

if [[ ! -d "$SRC_DIR/.git" ]]; then
  echo "Cloning ${GIT_REPO} into ${SRC_DIR}..."
  git clone --depth 50 "$GIT_REPO_AUTH" "$SRC_DIR"
fi

cd "$SRC_DIR"

# Keep origin URL current (supports repo rename / protocol switch).
git remote set-url origin "$GIT_REPO_AUTH" 2>/dev/null || git remote add origin "$GIT_REPO_AUTH"

# Shallow repos may not contain the SHA yet — deepen as needed.
if ! git cat-file -e "${GIT_SHA}^{commit}" 2>/dev/null; then
  echo "Fetching ${GIT_SHA}..."
  git fetch --depth 50 origin "$GIT_SHA" 2>/dev/null \
    || git fetch --depth 50 origin "+refs/heads/*:refs/remotes/origin/*" \
    || git fetch --unshallow origin 2>/dev/null \
    || git fetch origin
fi

if ! git cat-file -e "${GIT_SHA}^{commit}" 2>/dev/null; then
  echo "ERROR: commit ${GIT_SHA} not found in ${SRC_DIR} after fetch."
  exit 1
fi

git checkout --force --detach "$GIT_SHA"
# Drop untracked junk from prior deploys; keep ignored caches out of the image context.
git clean -fd

if [[ ! -f "$SRC_DIR/Dockerfile" ]]; then
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
  echo "ERROR: could not resolve image id for ${IMAGE_TAG}"
  exit 1
fi
echo "Built image id: ${EXPECTED_IMAGE_ID}"

# Hand off to the existing roll/verify script — skip GHCR login/pull.
export DEPLOY_PATH
export IMAGE="$IMAGE_TAG"
export LOCAL_IMAGE_BUILD=1
export DEPLOY_SCRIPT_DIR="$SCRIPT_DIR"
# Dummy values so ci-deploy-remote.sh's GHCR env checks are skipped when LOCAL_IMAGE_BUILD=1
export GHCR_USER="${GHCR_USER:-local}"
export GHCR_TOKEN_B64="${GHCR_TOKEN_B64:-}"

bash "${SCRIPT_DIR}/ci-deploy-remote.sh"

echo "Server-build deploy complete: ${IMAGE_TAG} (${EXPECTED_IMAGE_ID}) on $(hostname)"
