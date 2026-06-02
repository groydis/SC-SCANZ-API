# SCANZ spatial enrichment (derived XYZ)

Derived `world_position` for starmap locations (distance / nearest queries). Not official mobiGlas chart coordinates.

## Pipeline

```text
sc-data-unpack-script/export/v1/locations.json
        →  php artisan game:build-location-spatial
        →  data/enrichment/location-spatial.json

game:import-starmap {version}   (upstream scunpacked data)
        →  php artisan game:import-location-spatial {version}
        →  game_starmap_location_spatial table

GET /api/locations/{uuid|slug}  →  "spatial": { coordinate_space, world_position, source }
```

## Commands

### 1. Build bundle from export

From repo root, after `pnpm export` in `sc-data-unpack-script`.

The API container does not mount the sibling unpack repo. Copy export into mounted `storage/` first:

```powershell
# Host (Windows), from SC-SCANZ-API
Copy-Item ..\sc-data-unpack-script\export\v1\locations.json storage\app\export-locations.json

docker compose exec api php artisan game:build-location-spatial `
  --export=/var/www/html/storage/app/export-locations.json `
  --output=/var/www/html/storage/app/location-spatial.json

docker compose cp api:/var/www/html/storage/app/location-spatial.json data/enrichment/location-spatial.json
```

Or pass any path readable inside the container.

Options:

| Option | Default |
|--------|---------|
| `--export=` | `../sc-data-unpack-script/export/v1/locations.json` or `SCANZ_EXPORT_LOCATIONS_PATH` |
| `--output=` | `data/enrichment/location-spatial.json` |

### 2. Import into database

After `game:import-starmap` for the same version:

```bash
docker compose exec api php artisan migrate
# Copy committed bundle into storage if needed:
# docker compose cp data/enrichment/location-spatial.json api:/var/www/html/storage/app/location-spatial.json

docker compose exec api php artisan game:import-location-spatial 4.0.0-LIVE `
  --path=/var/www/html/storage/app/location-spatial.json

docker compose exec api php artisan game:import-location-spatial 4.0.0-LIVE --dry-run
```

Use your real `game:add-version` code instead of `4.0.0-LIVE`.

Skips:

- UUIDs not in `game_starmap_locations`
- Locations with no `game_starmap_location_data` row for that version

### 3. API

`spatial` is included on list and detail when imported. Example:

```json
"spatial": {
  "coordinate_space": "derived_system_v1",
  "world_position": { "x": 123, "y": 456, "z": 0 },
  "source": "sc-export-pipeline"
}
```

## English-only fork notes

- **Removed submodules:** `StarCitizenDeutsch`, `ScToolBoxLocales`
- **Required submodule:** `scunpacked-data` only

## Upstream sync

```bash
git fetch upstream
git merge upstream/develop
```

Keep SCANZ-specific files: `config/scanz.php`, `data/enrichment/`, `app/Console/Commands/Game/BuildLocationSpatial.php`, `ImportLocationSpatial.php`, migration `game_starmap_location_spatial`.
