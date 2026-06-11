<?php

declare(strict_types=1);

namespace App\Support\Starmap;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

/**
 * Builds starmap_positions.json-shaped data from scunpacked starmap.json + SCANZ location spatial enrichment.
 *
 * Used when storage/app/api/scunpacked-data/starmap_positions.json is not present.
 */
final class StarmapPositionBundle
{
    public const string SPATIAL_COORDINATE_SPACE = 'scanz_spatial_enrichment';

    /**
     * @return array{entities: list<array<string, mixed>>, connections: list<array<string, mixed>>}
     */
    public static function build(): array
    {
        $starmap = self::loadStarmapRows();
        $spatial = self::loadSpatialLocations();
        $raw = self::loadRawLocations();
        $byUuid = [];

        foreach ($starmap as $row) {
            if (! is_array($row)) {
                continue;
            }

            $uuid = strtolower((string) Arr::get($row, 'UUID', ''));

            if ($uuid !== '') {
                $byUuid[$uuid] = $row;
            }
        }

        $entities = [];

        foreach ($starmap as $row) {
            if (! is_array($row)) {
                continue;
            }

            $uuid = strtolower((string) Arr::get($row, 'UUID', ''));

            if ($uuid === '') {
                continue;
            }

            $coords = $spatial[$uuid] ?? null;

            if (! is_array($coords)) {
                continue;
            }

            $x = Arr::get($coords, 'x');
            $y = Arr::get($coords, 'y');
            $z = Arr::get($coords, 'z');

            if (! is_numeric($x) || ! is_numeric($y) || ! is_numeric($z)) {
                continue;
            }

            $systemUuid = strtolower((string) (Arr::get($coords, 'system_uuid') ?? ''));
            $system = self::resolveSystemName($systemUuid, $row, $byUuid);

            if ($system === null) {
                continue;
            }

            $type = Arr::get($row, 'Type');

            $entity = [
                'uuid' => $uuid,
                'name' => (string) (Arr::get($row, 'Name') ?? $uuid),
                'type' => is_array($type) ? (string) (Arr::get($type, 'Name') ?? 'Unknown') : 'Unknown',
                'system' => $system,
                'parent_uuid' => self::nullableUuid(Arr::get($row, 'ParentUUID')),
                'x' => (float) $x,
                'y' => (float) $y,
                'z' => (float) $z,
                'coordinate_space' => self::SPATIAL_COORDINATE_SPACE,
                'qt_valid' => self::isQtValid($row),
                'hidden' => self::isHidden($row),
            ];

            $rawCoords = $raw[$uuid] ?? null;

            if (is_array($rawCoords)) {
                $entity['raw_x'] = (float) Arr::get($rawCoords, 'x');
                $entity['raw_y'] = (float) Arr::get($rawCoords, 'y');
                $entity['raw_z'] = (float) Arr::get($rawCoords, 'z');
                $entity['raw_coordinate_space'] = StarmapRawPositionBundle::COORDINATE_SPACE;
            }

            $entities[] = $entity;
        }

        return [
            'entities' => $entities,
            'connections' => self::loadConnections(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadConnections(): array
    {
        $path = (string) config('scanz.starmap_raw_positions_bundle');

        if (! is_readable($path)) {
            return [];
        }

        try {
            return StarmapRawPositionBundle::loadConnections($path);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadStarmapRows(): array
    {
        $contents = Storage::disk('scunpacked')->get('starmap.json');

        if ($contents === null) {
            throw new RuntimeException('starmap.json not found on scunpacked disk.');
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('starmap.json is not valid JSON.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('starmap.json must decode to an array.');
        }

        return $decoded;
    }

    /**
     * @return array<string, array{x: float, y: float, z: float}>
     */
    private static function loadRawLocations(): array
    {
        $path = (string) config('scanz.starmap_raw_positions_bundle');

        if (! is_readable($path)) {
            return [];
        }

        try {
            return StarmapRawPositionBundle::loadLocations($path);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadSpatialLocations(): array
    {
        $path = (string) config('scanz.location_spatial_bundle');

        if (! is_readable($path)) {
            throw new RuntimeException("Location spatial bundle not readable: {$path}");
        }

        $bundle = LocationSpatialBundle::readFile($path);

        /** @var array<string, array<string, mixed>> $locations */
        $locations = Arr::get($bundle, 'locations', []);

        return $locations;
    }

    /**
     * @param  array<string, array<string, mixed>>  $byUuid
     */
    private static function resolveSystemName(
        string $systemUuid,
        array $row,
        array $byUuid,
    ): ?string {
        if ($systemUuid !== '' && isset($byUuid[$systemUuid])) {
            return self::normalizeSystemName((string) Arr::get($byUuid[$systemUuid], 'Name', ''));
        }

        $current = $row;

        for ($depth = 0; $depth < 16; $depth++) {
            $type = Arr::get($current, 'Type');
            $typeName = is_array($type) ? (string) (Arr::get($type, 'Name') ?? '') : '';

            if ($typeName === 'Star') {
                return self::normalizeSystemName((string) Arr::get($current, 'Name', ''));
            }

            $parentUuid = self::nullableUuid(Arr::get($current, 'ParentUUID'));

            if ($parentUuid === null || ! isset($byUuid[$parentUuid])) {
                break;
            }

            $current = $byUuid[$parentUuid];
        }

        return null;
    }

    private static function normalizeSystemName(string $name): ?string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return null;
        }

        return strtolower($trimmed);
    }

    private static function nullableUuid(mixed $value): ?string
    {
        $uuid = strtolower(trim((string) ($value ?? '')));

        return $uuid !== '' ? $uuid : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function isQtValid(array $row): bool
    {
        if ((bool) Arr::get($row, 'BlockTravel', false)) {
            return false;
        }

        $type = Arr::get($row, 'Type');

        if (! is_array($type)) {
            return false;
        }

        return (bool) Arr::get($type, 'ValidQuantumTravelDestination', false);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function isHidden(array $row): bool
    {
        return (bool) Arr::get($row, 'HideInStarmap', false)
            || (bool) Arr::get($row, 'HideInWorld', false);
    }
}
