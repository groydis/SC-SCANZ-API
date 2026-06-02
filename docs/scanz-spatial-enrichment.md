# SCANZ spatial enrichment (derived XYZ)

SCANZ-specific layer on top of upstream [StarCitizenWiki/API](https://github.com/StarCitizenWiki/API) starmap imports. Upstream owns game JSON → `game:import-starmap`; we add **derived world positions** for distance/nearest queries.

## Goals

1. Stay mergeable with `upstream/develop` (minimal PHP diffs).
2. Keep XYZ in versioned data under `data/enrichment/` (committed).
3. Import via a dedicated Artisan command (not mixed into `ImportStarmapData`).
4. Expose additive `spatial` on location API responses when present.

## Coordinate contract

| Field | Meaning |
|-------|---------|
| `coordinate_space` | `derived_system_v1` — heuristic placement from unpack export, not official mobiGlas chart coords |
| `world_position` | `{ x, y, z }` meters; use for distance sorting |
| `source` | e.g. `sc-export-pipeline`, `manual` |

Producing XYZ outside this API (PowerShell export in `sc-data-unpack-script`) is fine. Copy the generated bundle into `data/enrichment/location-spatial.json` and run the import command once it exists.

## English-only fork notes

- **Removed submodules:** `StarCitizenDeutsch`, `ScToolBoxLocales` (English-only labels).
- **Required submodule:** `scunpacked-data` only — `git submodule update --init storage/app/api/scunpacked-data`
- `game:import-labels` still works; DE/zh omitted when INI paths are absent.

## Upstream sync

```bash
git fetch upstream
git checkout develop
git merge upstream/develop
```

Resolve conflicts in PHP only when unavoidable; prefer keeping SCANZ changes in `data/enrichment/`, `docs/scanz-*.md`, and `app/Console/Commands/Game/ImportLocationSpatial.php` (planned).

## Local stack (first time)

From repo root ([SC-SCANZ-API](https://github.com/groydis/SC-SCANZ-API)):

```bash
git submodule update --init --recursive
cp .env.example .env
docker compose up -d
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate
docker compose exec api php artisan game:add-version --default <VERSION>
docker compose exec api php artisan db:seed
docker compose exec api php artisan game:sync
```

See [readme.md](../readme.md) and [commands.md](commands.md).

## Planned import flow

```bash
# 1. Place data/enrichment/location-spatial.json (uuid → world_position)
# 2. After game:import-starmap for that version:
docker compose exec api php artisan game:import-location-spatial {version}
```

## API shape (planned)

Additive field on `GET /api/.../locations/{identifier}`:

```json
"spatial": {
  "coordinate_space": "derived_system_v1",
  "world_position": { "x": 0, "y": 0, "z": 0 },
  "source": "sc-export-pipeline"
}
```

## What upstream already has

- `game_starmap_locations` + `game_starmap_location_data` (JSON `data` from scunpacked `starmap.json`)
- No derived XYZ today — see `StarmapLocationResource` (quantum travel radii only)
