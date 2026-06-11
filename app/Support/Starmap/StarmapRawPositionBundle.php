<?php

declare(strict_types=1);

namespace App\Support\Starmap;

use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

/**
 * Raw starmap-scale XYZ positions (Wiki route planner compatible).
 *
 * Stored separately from SCANZ derived spatial enrichment so Find Nearest ranking
 * and estimated QT can use different coordinate spaces.
 */
final class StarmapRawPositionBundle
{
    public const string COORDINATE_SPACE = 'raw_starmap';

    /**
     * @return array{
     *     version: int,
     *     coordinate_space: string,
     *     updated_at?: string,
     *     source?: string,
     *     locations: array<string, array{x: float, y: float, z: float}>,
     *     connections?: list<array<string, mixed>>
     * }
     */
    public static function readFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Starmap raw position bundle not readable: {$path}");
        }

        try {
            $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid starmap raw position bundle JSON: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($decoded) || ! is_array($decoded['locations'] ?? null)) {
            throw new RuntimeException('Starmap raw position bundle must contain a locations object.');
        }

        return $decoded;
    }

    /**
     * @param  array{
     *     version: int,
     *     coordinate_space: string,
     *     updated_at?: string,
     *     source?: string,
     *     locations: array<string, array<string, mixed>>,
     *     connections?: list<array<string, mixed>>
     * }  $bundle
     */
    public static function writeFile(string $path, array $bundle): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create directory: {$directory}");
        }

        $encoded = json_encode($bundle, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $encoded."\n");
    }

    /**
     * @return array<string, array{x: float, y: float, z: float}>
     */
    public static function loadLocations(string $path): array
    {
        $bundle = self::readFile($path);
        $normalized = [];

        /** @var array<string, array<string, mixed>> $locations */
        $locations = Arr::get($bundle, 'locations', []);

        foreach ($locations as $uuid => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = strtolower(trim((string) $uuid));
            $x = Arr::get($entry, 'x');
            $y = Arr::get($entry, 'y');
            $z = Arr::get($entry, 'z');

            if ($key === '' || ! is_numeric($x) || ! is_numeric($y) || ! is_numeric($z)) {
                continue;
            }

            $normalized[$key] = [
                'x' => (float) $x,
                'y' => (float) $y,
                'z' => (float) $z,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{
     *     entry_uuid: string,
     *     exit_uuid: string,
     *     entry_system: string,
     *     exit_system: string,
     *     fuel_cost: int,
     *     size_class: string
     * }>
     */
    public static function loadConnections(string $path): array
    {
        $bundle = self::readFile($path);

        /** @var list<array<string, mixed>> $connections */
        $connections = Arr::get($bundle, 'connections', []);

        return self::normalizeWikiConnections($connections);
    }

    /**
     * @param  list<array<string, mixed>>  $connections
     * @return list<array{
     *     entry_uuid: string,
     *     exit_uuid: string,
     *     entry_system: string,
     *     exit_system: string,
     *     fuel_cost: int,
     *     size_class: string
     * }>
     */
    public static function normalizeWikiConnections(array $connections): array
    {
        $normalized = [];

        foreach ($connections as $row) {
            if (! is_array($row)) {
                continue;
            }

            $entryUuid = strtolower(trim((string) Arr::get($row, 'entry_uuid', '')));
            $exitUuid = strtolower(trim((string) Arr::get($row, 'exit_uuid', '')));
            $entrySystem = strtolower(trim((string) Arr::get($row, 'entry_system', '')));
            $exitSystem = strtolower(trim((string) Arr::get($row, 'exit_system', '')));

            if ($entryUuid === '' || $exitUuid === '' || $entrySystem === '' || $exitSystem === '') {
                continue;
            }

            $sizeRaw = strtolower(trim((string) Arr::get($row, 'size_class', 'unknown')));
            $sizeClass = in_array($sizeRaw, ['small', 'large'], true) ? $sizeRaw : 'unknown';

            $normalized[] = [
                'entry_uuid' => $entryUuid,
                'exit_uuid' => $exitUuid,
                'entry_system' => $entrySystem,
                'exit_system' => $exitSystem,
                'fuel_cost' => (int) (Arr::get($row, 'fuel_cost') ?? 0),
                'size_class' => $sizeClass,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $wikiEntities
     * @param  list<array<string, mixed>>  $wikiConnections
     * @return array{
     *     version: int,
     *     coordinate_space: string,
     *     updated_at: string,
     *     source: string,
     *     locations: array<string, array{x: float, y: float, z: float}>,
     *     connections: list<array<string, mixed>>
     * }
     */
    public static function fromWikiPayload(array $wikiEntities, array $wikiConnections = []): array
    {
        $locations = [];

        foreach ($wikiEntities as $row) {
            if (! is_array($row)) {
                continue;
            }

            $uuid = strtolower(trim((string) Arr::get($row, 'uuid', '')));
            $x = Arr::get($row, 'x');
            $y = Arr::get($row, 'y');
            $z = Arr::get($row, 'z');

            if ($uuid === '' || ! is_numeric($x) || ! is_numeric($y) || ! is_numeric($z)) {
                continue;
            }

            $locations[$uuid] = [
                'x' => (float) $x,
                'y' => (float) $y,
                'z' => (float) $z,
            ];
        }

        return [
            'version' => 1,
            'coordinate_space' => self::COORDINATE_SPACE,
            'updated_at' => now()->toIso8601String(),
            'source' => 'star-citizen-wiki-api',
            'locations' => $locations,
            'connections' => self::normalizeWikiConnections($wikiConnections),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $wikiEntities
     * @return array{
     *     version: int,
     *     coordinate_space: string,
     *     updated_at: string,
     *     source: string,
     *     locations: array<string, array{x: float, y: float, z: float}>,
     *     connections: list<array<string, mixed>>
     * }
     */
    public static function fromWikiEntities(array $wikiEntities): array
    {
        return self::fromWikiPayload($wikiEntities, []);
    }
}
