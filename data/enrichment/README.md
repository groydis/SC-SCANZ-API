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

Generate from the export pipeline (`spatial.worldPosition` in `locations.json`) or maintain manually. Import: `php artisan game:import-location-spatial {version}` (command TBD — see [docs/scanz-spatial-enrichment.md](../docs/scanz-spatial-enrichment.md)).
