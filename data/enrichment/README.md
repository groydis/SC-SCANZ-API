# Enrichment data (SCANZ)

Committed overlays not present in scunpacked game dumps.

## `location-spatial.json`

```json
{
  "version": 1,
  "coordinate_space": "derived_system_v1",
  "locations": {
    "c6535844-c615-494e-9dc1-c2d0c6e7190a": {
      "x": 12345678.0,
      "y": 9876543.0,
      "z": 0.0,
      "source": "sc-export-pipeline"
    }
  }
}
```

Generate from the export pipeline (`spatial.worldPosition` in `locations.json`) or maintain manually. Build from export: `php artisan game:build-location-spatial`

Import: `php artisan game:import-location-spatial {version}`

See [docs/scanz-spatial-enrichment.md](../docs/scanz-spatial-enrichment.md).

## `starmap-raw-positions.json`

Raw starmap-scale XYZ used for estimated quantum travel distance (Wiki route planner compatible). Separate from `location-spatial.json` derived enrichment used for Find Nearest spatial ranking.

```json
{
  "version": 1,
  "coordinate_space": "raw_starmap",
  "source": "star-citizen-wiki-api",
  "locations": {
    "35a9f8f5-8f80-4796-83db-7d7362baeb9a": {
      "x": 18587664739.85602,
      "y": -22151916920.3126,
      "z": 0
    }
  }
}
```

Build from Wiki positions API:

```bash
php artisan game:build-starmap-raw-positions
php artisan game:build-starmap-positions
# Fly deploy reads a copy under data/enrichment/ (see Dockerfile.fly):
cp storage/app/api/scunpacked-data/starmap_positions.json data/enrichment/starmap-positions.json
```

`/api/locations/positions` exposes enriched coords in `x/y/z` (`coordinate_space: scanz_spatial_enrichment`) and raw coords in `raw_x/raw_y/raw_z` when available.
