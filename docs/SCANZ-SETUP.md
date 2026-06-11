# SCANZ API — setup guide

Fork-specific steps for [groydis/SC-SCANZ-API](https://github.com/groydis/SC-SCANZ-API). Upstream docs: [readme.md](../readme.md), [docker.md](docker.md), [configuration.md](configuration.md).

You do **not** need Star Citizen installed or `sc-data-unpack-script` on the target machine if this repo already contains `data/enrichment/location-spatial.json` and you init the `scunpacked-data` submodule.

## Prerequisites

- Git
- Docker + Docker Compose 2.x

## 1. Clone and submodules

```bash
git clone https://github.com/groydis/SC-SCANZ-API.git
cd SC-SCANZ-API
git checkout develop

# Game JSON only (English-only fork — skip DE/zh submodules)
git submodule update --init storage/app/api/scunpacked-data
```

Optional upstream remote for merges:

```bash
git remote add upstream https://github.com/StarCitizenWiki/API.git
git fetch upstream
```

## 2. Environment

```bash
cp .env.example .env
```

Minimum for Docker (see [configuration.md](configuration.md) for full list):

```dotenv
APP_NAME='Star Citizen API'
APP_URL=http://localhost:8080
APP_KEY=base64:...   # see below

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=api
DB_USERNAME=api
DB_PASSWORD=secret

POSTGRES_DB=api
POSTGRES_USER=api
POSTGRES_PASSWORD=secret

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Generate `APP_KEY`:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
# or after containers are up:
docker compose exec api php artisan key:generate --show
```

## 3. Docker

```bash
docker compose build api queue queue_expensive
docker compose up -d
```

Confirm `api` is **Up** (`docker compose ps`). The image strips CRLF from `docker/start.sh` (required on Windows checkouts).

## 4. Database and game data

Use a version code that exists in `scunpacked-data` (same as on an existing deploy, e.g. `4.8.0-LIVE.11875683`):

```bash
docker compose exec api php artisan migrate
docker compose exec api php artisan game:add-version --default <VERSION>
docker compose exec api php artisan db:seed
docker compose exec api php artisan game:sync
```

`game:sync` imports starmap, items, labels, etc. from **scunpacked-data** — not from local game files.

## 5. Spatial enrichment (derived XYZ)

Import the committed bundle (no export pipeline required):

```bash
docker compose cp data/enrichment/location-spatial.json api:/var/www/html/storage/app/location-spatial.json

docker compose exec api php artisan game:import-location-spatial <VERSION> \
  --path=/var/www/html/storage/app/location-spatial.json
```

## 6. Clean location slugs

Avoid `arccorp-2` style numeric suffixes:

```bash
docker compose exec api php artisan game:repair-starmap-slugs <VERSION> --dry-run
docker compose exec api php artisan game:repair-starmap-slugs <VERSION>
```

## 7. Verify

| What | URL |
|------|-----|
| Web UI | http://localhost:8080 |
| ArcCorp | http://localhost:8080/locations/arccorp |
| JSON + `spatial` | http://localhost:8080/api/locations/arccorp |

## Regenerating XYZ (optional)

Only needed on a machine **with** [sc-data-unpack-script](https://github.com/groydis/sc-data-unpack-script) and a fresh `export/v1/locations.json`:

```bash
# Copy export into mounted storage, then:
docker compose exec api php artisan game:build-location-spatial \
  --export=/var/www/html/storage/app/export-locations.json \
  --output=/var/www/html/storage/app/location-spatial.json
```

Commit updated `data/enrichment/location-spatial.json`, then other machines repeat **§5** only.

Details: [scanz-spatial-enrichment.md](scanz-spatial-enrichment.md).

## What not to commit

- `.env`
- `storage/` (except tracked paths), `var/lib/db/`
- Docker volume data

## Production (Fly read API + Neon game DB)

Local import → publish DB → thin Fly deploy. See [fly.md](fly.md) and `scripts/publish-game-db.sh`.

## Related docs

| Doc | Contents |
|-----|----------|
| [fly.md](fly.md) | Read-only Fly deploy, Neon publish workflow |
| [scanz-spatial-enrichment.md](scanz-spatial-enrichment.md) | Pipeline, API shape, upstream sync |
| [data/enrichment/README.md](../data/enrichment/README.md) | Bundle JSON format |
| [commands.md](commands.md) | All Artisan commands |
| [docker.md](docker.md) | Container layout |
