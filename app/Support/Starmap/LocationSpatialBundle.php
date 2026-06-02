<?php

declare(strict_types=1);

namespace App\Support\Starmap;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonException;
use RuntimeException;

final class LocationSpatialBundle
{
    public const string DEFAULT_COORDINATE_SPACE = 'derived_system_v1';

    /**
     * @return array{
     *     version: int,
     *     coordinate_space: string,
     *     updated_at: string,
     *     locations: array<string, array{x: float, y: float, z: float, source?: string, system_uuid?: string|null}>
     * }
     */
    public static function fromExportLocations(array $locations): array
    {
        $coordinateSpace = self::DEFAULT_COORDINATE_SPACE;
        $entries = [];

        foreach ($locations as $location) {
            if (! is_array($location)) {
                continue;
            }

            $uuid = strtolower((string) Arr::get($location, 'uuid', ''));
            $world = Arr::get($location, 'spatial.worldPosition');

            if ($uuid === '' || ! is_array($world)) {
                continue;
            }

            $x = Arr::get($world, 'x');
            $y = Arr::get($world, 'y');
            $z = Arr::get($world, 'z');

            if (! is_numeric($x) || ! is_numeric($y) || ! is_numeric($z)) {
                continue;
            }

            $entries[$uuid] = [
                'x' => (float) $x,
                'y' => (float) $y,
                'z' => (float) $z,
                'source' => 'sc-export-pipeline',
                'system_uuid' => Arr::get($location, 'systemUuid'),
            ];
        }

        return [
            'version' => 1,
            'coordinate_space' => $coordinateSpace,
            'updated_at' => now()->toIso8601String(),
            'locations' => $entries,
        ];
    }

    /**
     * @return array{
     *     version: int,
     *     coordinate_space: string,
     *     updated_at?: string,
     *     locations: array<string, array<string, mixed>>
     * }
     */
    public static function readFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Location spatial bundle not readable: {$path}");
        }

        try {
            $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid location spatial bundle JSON: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($decoded) || ! is_array($decoded['locations'] ?? null)) {
            throw new RuntimeException('Location spatial bundle must contain a locations object.');
        }

        return $decoded;
    }

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
     * @param  array<string, array<string, mixed>>  $locations
     * @return Collection<int, array{uuid: string, x: float, y: float, z: float, source: string, system_uuid: ?string}>
     */
    public static function normalizedEntries(array $locations): Collection
    {
        return collect($locations)
            ->map(static function (mixed $entry, string $uuid): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $x = Arr::get($entry, 'x');
                $y = Arr::get($entry, 'y');
                $z = Arr::get($entry, 'z');

                if (! is_numeric($x) || ! is_numeric($y) || ! is_numeric($z)) {
                    return null;
                }

                return [
                    'uuid' => strtolower($uuid),
                    'x' => (float) $x,
                    'y' => (float) $y,
                    'z' => (float) $z,
                    'source' => is_string(Arr::get($entry, 'source')) ? (string) Arr::get($entry, 'source') : 'sc-export-pipeline',
                    'system_uuid' => is_string(Arr::get($entry, 'system_uuid')) ? (string) Arr::get($entry, 'system_uuid') : null,
                ];
            })
            ->filter()
            ->values();
    }
}
