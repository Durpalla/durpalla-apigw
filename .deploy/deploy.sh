#!/usr/bin/env bash
# Server-side deploy wrapper (pull/build image + restart container).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec "$ROOT/script/docker-deploy.sh"
