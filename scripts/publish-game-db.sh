#!/usr/bin/env bash
# Publish local SC-SCANZ-API Postgres to Neon game database.
#
# Usage:
#   export NEON_GAME_DIRECT_URL='postgresql://...'   # direct (non-pooler) Neon URL
#   ./scripts/publish-game-db.sh
#
# Optional:
#   SKIP_DUMP=1          reuse ./scanz-game.dump
#   FLY_DEPLOY=1         fly deploy after restore (when JSON artifacts changed)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -z "${NEON_GAME_DIRECT_URL:-}" ]]; then
  echo "Set NEON_GAME_DIRECT_URL to the Neon direct connection string (not the pooler)." >&2
  exit 1
fi

DUMP="${ROOT}/scanz-game.dump"

if [[ "${SKIP_DUMP:-}" != "1" ]]; then
  echo "Dumping local game database..."
  docker compose exec -T db pg_dump -U api -d api \
    --no-owner --no-acl --format=custom > "$DUMP"
  echo "Wrote $(du -h "$DUMP" | cut -f1) dump to $DUMP"
fi

if [[ ! -s "$DUMP" ]]; then
  echo "Missing dump: $DUMP" >&2
  exit 1
fi

echo "Restoring to Neon (direct URL)..."
docker run --rm \
  -v "${DUMP}:/dump.dump:ro" \
  postgres:16 \
  pg_restore --clean --if-exists --no-owner --no-acl \
    -d "$NEON_GAME_DIRECT_URL" /dump.dump

echo "Restore complete."

if [[ "${FLY_DEPLOY:-}" == "1" ]]; then
  echo "Redeploying scanz-api..."
  fly deploy
fi

echo "Smoke:"
echo "  curl -fsS \"\${SCANZ_API_URL:-https://scanz-api.fly.dev}/up\""
