# Fly.io — read-only game API

Production model: **import locally** (Docker Compose), **publish Postgres to Neon**, serve reads from a **thin Fly app** (`scanz-api`).

## Prerequisites

- Local pipeline complete (`docs/SCANZ-SETUP.md`)
- `data/enrichment/starmap-positions.json` on the build host (copy from `storage/.../starmap_positions.json` after `game:build-starmap-positions`)
- Neon **game** database in **`aws-ap-southeast-2`** (same region as Fly `syd`) — separate from scanz-space community DB
- [flyctl](https://fly.io/docs/flyctl/install/) authenticated

## One-time: Fly app

```bash
fly apps create scanz-api   # if not created
fly certs add api.scanz.space   # optional custom host
```

## Secrets

Set on `scanz-api` (values from Neon console — **never commit**):

```bash
fly secrets set \
  APP_KEY='base64:...' \
  APP_URL='https://scanz-api.fly.dev' \
  DB_HOST='ep-....c-3.us-east-2.aws.neon.tech' \
  DB_DATABASE='neondb' \
  DB_USERNAME='neondb_owner' \
  DB_PASSWORD='...' \
  -a scanz-api
```

Use the **pooled** hostname (`...-pooler....`) for `DB_HOST` on Fly. Use the **direct** URL only for `pg_restore` (see below).

Generate `APP_KEY` if needed:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

## Publish game database (repeat per patch)

```bash
export NEON_GAME_DIRECT_URL='postgresql://...@ep-....neon.tech/neondb?sslmode=require'
chmod +x scripts/publish-game-db.sh
./scripts/publish-game-db.sh
```

Direct URL: same host as pooled but **without** `-pooler` in the hostname.

After spatial / `starmap_positions.json` changes, rebuild and redeploy:

```bash
docker compose exec api php artisan game:build-starmap-positions
FLY_DEPLOY=1 ./scripts/publish-game-db.sh   # or fly deploy separately
```

## Deploy API

```bash
fly deploy
curl -fsS https://scanz-api.fly.dev/up
curl -fsS https://scanz-api.fly.dev/api/locations/positions | head
```

## Wire scanz-space

```bash
fly secrets set SCANZ_GAME_API_URL='https://scanz-api.fly.dev' -a scanz-space
```

Validate from scanz-space repo:

```bash
FIND_NEAREST_SANITY_URL=https://scanz.space \
  SCANZ_GAME_API_URL=https://scanz-api.fly.dev \
  pnpm validate:find-nearest
```

## What the Fly image includes

| Included | Not included |
| -------- | ------------ |
| Laravel app + Vite assets | Queue workers |
| `data/enrichment/` | Full `scunpacked-data` submodule (~3.4 GB) |
| `starmap_positions.json` | Local Postgres |
| `location-spatial.json` copy in `storage/app/` | Import commands at runtime |

Game rows come from Neon after `publish-game-db.sh`. The positions endpoint reads `starmap_positions.json` from the image.

## Troubleshooting

| Issue | Fix |
| ----- | --- |
| Build fails: missing `starmap_positions.json` | Run `game:build-starmap-positions` locally first |
| `/api/locations/positions` 503 | Rebuild image after regenerating positions JSON |
| DB errors on Fly | Check pooled `DB_*` secrets; Neon project must be game DB |
| Stale data | Re-run local sync + `./scripts/publish-game-db.sh` |
