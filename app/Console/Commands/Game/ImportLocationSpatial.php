<?php

declare(strict_types=1);

namespace App\Console\Commands\Game;

use App\Models\Game\GameVersion;
use App\Services\Game\ImportLocationSpatial as ImportLocationSpatialService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use function Laravel\Prompts\select;

class ImportLocationSpatial extends Command implements PromptsForMissingInput
{
    protected $signature = 'game:import-location-spatial
                            {version : Game version code to attach spatial data to}
                            {--path= : Bundle JSON path (default: data/enrichment/location-spatial.json)}
                            {--dry-run : Report counts without writing}';

    protected $description = 'Import derived XYZ from data/enrichment/location-spatial.json into the database';

    public function handle(ImportLocationSpatialService $importer): int
    {
        $versionCode = (string) $this->argument('version');
        $bundlePath = (string) ($this->option('path') ?: config('scanz.location_spatial_bundle'));
        $dryRun = (bool) $this->option('dry-run');

        $gameVersion = GameVersion::query()->where('code', $versionCode)->first();

        if ($gameVersion === null) {
            $this->error(sprintf('Game version "%s" does not exist. Run game:add-version first.', $versionCode));

            return self::FAILURE;
        }

        if (! is_readable($bundlePath)) {
            $this->error("Bundle not found: {$bundlePath}");
            $this->line('Run: php artisan game:build-location-spatial');

            return self::FAILURE;
        }

        $entryCount = $importer->entryCount($bundlePath);
        $this->info(sprintf('Bundle contains %d spatial entries.', $entryCount));

        $stats = $importer->import($gameVersion, $bundlePath, $dryRun);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported (or would import)', $stats['imported']],
                ['Skipped — unknown location UUID', $stats['skipped_no_location']],
                ['Skipped — no row for this game version', $stats['skipped_no_version_data']],
            ],
        );

        if ($dryRun) {
            $this->warn('Dry run — no database changes written.');
        } else {
            $this->info(sprintf('Spatial data imported for version %s.', $gameVersion->code));
        }

        return self::SUCCESS;
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'version' => function (): string {
                $options = GameVersion::query()
                    ->orderByDesc('released_at')
                    ->orderBy('code')
                    ->pluck('code', 'code')
                    ->toArray();

                if ($options === []) {
                    $this->error('No game versions exist. Please create one before importing.');

                    return '';
                }

                return select(
                    label: 'Select game version for spatial import',
                    options: $options,
                );
            },
        ];
    }
}
