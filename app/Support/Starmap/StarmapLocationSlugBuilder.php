<?php

declare(strict_types=1);

namespace App\Support\Starmap;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Prefer clean slugs (arccorp, stanton) for the most relevant entity per display name.
 * Duplicates use type suffixes (stanton-solarsystem) instead of numeric -2, -3.
 */
final class StarmapLocationSlugBuilder
{
    /** Lower value wins the bare name when multiple entities share the same display name. */
    private const array TYPE_PRIORITY = [
        'LandingZone' => 10,
        'Planet' => 20,
        'Moon' => 30,
        'Star' => 40,
        'SolarSystem' => 50,
        'Asteroid' => 60,
        'AsteroidCluster' => 65,
        'Manmade' => 70,
        'SpaceStation' => 75,
        'Outpost' => 80,
        'PointOfInterest' => 85,
        'NavPoint' => 90,
        'JumpPoint' => 95,
        'Anomaly' => 100,
    ];

    /**
     * @param  array<string, array<string, mixed>>  $entries  keyed by location UUID
     * @return array<string, string>  uuid => slug
     */
    public function build(array $entries): array
    {
        $byBaseName = [];

        foreach ($entries as $uuid => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $base = Str::slug($this->extractName($entry));

            if ($base === '') {
                $base = Str::slug((string) $uuid);
            }

            $byBaseName[$base][] = (string) $uuid;
        }

        $slugMap = [];
        $usedSlugs = [];

        foreach ($byBaseName as $base => $uuids) {
            if (count($uuids) === 1) {
                $slugMap[$uuids[0]] = $this->claimSlug($base, $usedSlugs);

                continue;
            }

            usort(
                $uuids,
                fn (string $a, string $b): int => $this->typePriority($entries[$a])
                    <=> $this->typePriority($entries[$b])
            );

            foreach ($uuids as $index => $uuid) {
                $candidate = $index === 0
                    ? $base
                    : $base.'-'.$this->typeSlugSuffix($entries[$uuid]);

                $slugMap[$uuid] = $this->claimSlug($candidate, $usedSlugs, $entries[$uuid]);
            }
        }

        return $slugMap;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function extractName(array $entry): string
    {
        $name = $entry['Name'] ?? null;

        return is_string($name) && $name !== '' ? $name : (string) ($entry['UUID'] ?? 'location');
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function typePriority(array $entry): int
    {
        $typeName = (string) Arr::get($entry, 'Type.Name', 'Unknown');

        return self::TYPE_PRIORITY[$typeName] ?? 500;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function typeSlugSuffix(array $entry): string
    {
        $typeName = (string) Arr::get($entry, 'Type.Name', 'unknown');
        $suffix = Str::slug($typeName);

        if ($suffix !== '') {
            return $suffix;
        }

        $tag = Arr::get($entry, 'LocationHierarchyTag.Name');

        if (is_string($tag) && $tag !== '') {
            return Str::slug($tag);
        }

        return 'location';
    }

    /**
     * @param  array<string, mixed>|null  $entry
     */
    private function claimSlug(string $preferred, array &$usedSlugs, ?array $entry = null): string
    {
        if (! in_array($preferred, $usedSlugs, true)) {
            $usedSlugs[] = $preferred;

            return $preferred;
        }

        if ($entry !== null) {
            $withType = $preferred.'-'.$this->typeSlugSuffix($entry);

            if (! in_array($withType, $usedSlugs, true)) {
                $usedSlugs[] = $withType;

                return $withType;
            }
        }

        $tag = is_array($entry) ? Arr::get($entry, 'LocationHierarchyTag.Name') : null;

        if (is_string($tag) && $tag !== '') {
            $withTag = $preferred.'-'.Str::slug($tag);

            if (! in_array($withTag, $usedSlugs, true)) {
                $usedSlugs[] = $withTag;

                return $withTag;
            }
        }

        $counter = 2;

        while (true) {
            $fallback = $preferred.'-'.$counter;

            if (! in_array($fallback, $usedSlugs, true)) {
                $usedSlugs[] = $fallback;

                return $fallback;
            }

            $counter++;
        }
    }
}
