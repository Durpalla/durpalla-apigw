#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Copy .env.docker.example to .env first." >&2
  exit 1
fi

COMPOSE="docker-compose"
if docker compose version >/dev/null 2>&1; then
  COMPOSE="docker compose"
fi

$COMPOSE -f docker-compose.local.yml up --build "$@"
