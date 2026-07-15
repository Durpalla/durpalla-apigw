#!/usr/bin/env bash
# IP of the Docker host as seen from containers on the default bridge network.
set -euo pipefail

if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
  gw="$(docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}' 2>/dev/null || true)"
  if [[ -n "$gw" && "$gw" != "<no value>" ]]; then
    echo "$gw"
    exit 0
  fi
fi

if command -v ip >/dev/null 2>&1; then
  ip -4 route show default 2>/dev/null | awk '{print $3; exit}'
  exit 0
fi

echo "172.17.0.1"
