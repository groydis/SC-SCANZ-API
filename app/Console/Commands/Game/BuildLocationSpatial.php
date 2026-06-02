<?php

declare(strict_types=1);

namespace App\Console\Commands\Game;

use App\Support\Starmap\LocationSpatialBundle;
use Illuminate\Console\Command;
use JsonException;

class BuildLocationSpatial extends Command
{
    protected $signature = 'game:build-location-spatial
                            {--export= : Path to export/v1/locations.json (array)}
                            {--output= : Output bundle path (default: data/enrichment/location-spatial.json)}';

    protected $description = 'Build data/enrichment/location-spatial.json from sc-data-unpack export locations';

    public function handle(): int
    {
        $exportPath = (string) ($this->option('export') ?: config('scanz.export_locations_path'));
        $outputPath = (string) ($this->option('output') ?: config('scanz.location_spatial_bundle'));

        if (! is_readable($exportPath)) {
            $this->error("Export file not found: {$exportPath}");
            $this->line('Run the export pipeline in sc-data-unpack-script (pnpm export) or pass --export=');

            return self::FAILURE;
        }

        try {
            $locations = json_decode(file_get_contents($exportPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Invalid export JSON: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($locations)) {
            $this->error('Export locations file must be a JSON array.');

            return self::FAILURE;
        }

        $bundle = LocationSpatialBundle::fromExportLocations($locations);
        $count = count($bundle['locations']);

        if ($count === 0) {
            $this->warn('No locations with spatial.worldPosition found in export.');

            return self::FAILURE;
        }

        LocationSpatialBundle::writeFile($outputPath, $bundle);

        $this->info(sprintf('Wrote %d spatial entries → %s', $count, $outputPath));

        return self::SUCCESS;
    }
}
