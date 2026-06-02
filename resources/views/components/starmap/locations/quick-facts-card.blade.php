@props([
    'location',
    'resolvedVersion' => null,
])

@php
    $locationUuid = data_get($location, 'uuid');
    $locationSlug = data_get($location, 'slug');
    $starName = data_get($location, 'star.name');
    $starSlug = data_get($location, 'star.slug');
    $starUuid = data_get($location, 'star.uuid');
    $parentName = data_get($location, 'parent.name');
    $parentSlug = data_get($location, 'parent.slug');
    $parentUuid = data_get($location, 'parent.uuid');

    $withVersion = static function (string $url) use ($resolvedVersion): string {
        if (! is_string($resolvedVersion) || $resolvedVersion === '') {
            return $url;
        }

        return url()->query($url, ['version' => $resolvedVersion]);
    };

    $starUrl = null;
    if (is_string($starUuid) && $starUuid !== '' && $starUuid !== $locationUuid) {
        $starUrl = $withVersion(route('web.locations.show', ['identifier' => $starSlug ?: $starUuid]));
    }

    $parentUrl = null;
    if (is_string($parentUuid) && $parentUuid !== '' && $parentUuid !== $locationUuid) {
        $parentUrl = $withVersion(route('web.locations.show', ['identifier' => $parentSlug ?: $parentUuid]));
    }

    $spatial = data_get($location, 'spatial');
    $spatialRows = [];
    if (is_array($spatial) && is_array(data_get($spatial, 'world_position'))) {
        $wp = data_get($spatial, 'world_position');
        $spatialRows = [
            ['label' => 'Space', 'value' => data_get($spatial, 'coordinate_space', '-')],
            ['label' => 'X', 'value' => number_format((float) data_get($wp, 'x', 0), 3, '.', ',')],
            ['label' => 'Y', 'value' => number_format((float) data_get($wp, 'y', 0), 3, '.', ',')],
            ['label' => 'Z', 'value' => number_format((float) data_get($wp, 'z', 0), 3, '.', ',')],
            ['label' => 'Source', 'value' => data_get($spatial, 'source', '-')],
        ];
    }

    $columns = [
        [
            'title' => 'Hierarchy',
            'rows' => [
                $starUrl !== null
                    ? ['label' => 'Star', 'value' => $starName ?? '-', 'type' => 'link', 'url' => $starUrl, 'test_id' => 'starmap-location-quick-facts-star-link']
                    : ['label' => 'Star', 'value' => $starName ?? '-'],
                ['label' => 'System', 'value' => data_get($location, 'system', '-')],
                $parentUrl !== null
                    ? ['label' => 'Parent', 'value' => $parentName ?? '-', 'type' => 'link', 'url' => $parentUrl, 'test_id' => 'starmap-location-quick-facts-parent-link']
                    : ['label' => 'Parent', 'value' => $parentName ?? '-'],
            ],
        ],
        [
            'title' => 'Status',
            'rows' => [
                ['label' => 'Starmap', 'value' => data_get($location, 'hide_in_starmap') ? 'Hidden' : 'Visible'],
                ['label' => 'World', 'value' => data_get($location, 'hide_in_world') ? 'Hidden' : 'Visible'],
                ['label' => 'Scannable', 'value' => data_get($location, 'is_scannable') ? 'Yes' : 'No'],
                ['label' => 'Travel', 'value' => data_get($location, 'block_travel') ? 'Blocked' : 'Allowed'],
            ],
        ],
        [
            'title' => 'Overview',
            'rows' => [
                ['label' => 'Children', 'value' => (string) data_get($location, 'child_count', 0)],
                ['label' => 'Missions', 'value' => (string) data_get($location, 'mission_count', 0)],
                ['label' => 'Respawn', 'value' => data_get($location, 'respawn_location_type', '-')],
            ],
        ],
        ...($spatialRows !== []
            ? [['title' => 'Spatial (SCANZ)', 'rows' => $spatialRows]]
            : []),
    ];

    $uuidApiUrl = is_string($locationUuid) && $locationUuid !== ''
        ? route('locations.show', $locationUuid)
        : null;

    $footer = [
        $locationUuid !== null
            ? ['label' => 'UUID', 'value' => $locationUuid, 'url' => $uuidApiUrl]
            : ['label' => 'UUID', 'value' => '-'],
        ['label' => 'Version', 'value' => data_get($location, 'version', '-')],
    ];
@endphp
<x-quick-facts-card :columns="$columns" :footer="$footer" :test-id="'starmap-location-quick-facts'" {{ $attributes }} />
