<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Models\Game\GameVersion;
use App\Models\Game\StarmapLocation;
use App\Models\Game\StarmapLocationData;
use App\Models\Game\StarmapLocationSpatial;
use App\Support\Starmap\LocationSpatialBundle;
use Illuminate\Support\Collection;

final class ImportLocationSpatial
{
    /**
     * @return array{imported: int, skipped_no_location: int, skipped_no_version_data: int}
     */
    public function import(GameVersion $gameVersion, string $bundlePath, bool $dryRun = false): array
    {
        $bundle = LocationSpatialBundle::readFile($bundlePath);
        $coordinateSpace = is_string($bundle['coordinate_space'] ?? null)
            ? (string) $bundle['coordinate_space']
            : LocationSpatialBundle::DEFAULT_COORDINATE_SPACE;

        $entries = LocationSpatialBundle::normalizedEntries($bundle['locations']);
        $uuids = $entries->pluck('uuid')->all();

        $locationIdByUuid = StarmapLocation::query()
            ->whereIn('uuid', $uuids)
            ->pluck('id', 'uuid');

        $locationDataByLocationId = StarmapLocationData::query()
            ->where('game_version_id', $gameVersion->id)
            ->whereIn('starmap_location_id', $locationIdByUuid->values())
            ->get(['id', 'starmap_location_id'])
            ->keyBy('starmap_location_id');

        $imported = 0;
        $skippedNoLocation = 0;
        $skippedNoVersionData = 0;

        foreach ($entries as $entry) {
            $locationId = $locationIdByUuid->get($entry['uuid']);

            if ($locationId === null) {
                $skippedNoLocation++;

                continue;
            }

            $locationData = $locationDataByLocationId->get($locationId);

            if ($locationData === null) {
                $skippedNoVersionData++;

                continue;
            }

            if (! $dryRun) {
                StarmapLocationSpatial::query()->updateOrCreate(
                    ['starmap_location_data_id' => $locationData->id],
                    [
                        'coordinate_space' => $coordinateSpace,
                        'position_x' => $entry['x'],
                        'position_y' => $entry['y'],
                        'position_z' => $entry['z'],
                        'source' => $entry['source'],
                        'system_uuid' => $entry['system_uuid'],
                    ],
                );
            }

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped_no_location' => $skippedNoLocation,
            'skipped_no_version_data' => $skippedNoVersionData,
        ];
    }

    public function entryCount(string $bundlePath): int
    {
        $bundle = LocationSpatialBundle::readFile($bundlePath);

        return LocationSpatialBundle::normalizedEntries($bundle['locations'])->count();
    }
}
