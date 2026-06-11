<?php

declare(strict_types=1);

return [
    'location_spatial_bundle' => base_path('data/enrichment/location-spatial.json'),

    'starmap_raw_positions_bundle' => base_path('data/enrichment/starmap-raw-positions.json'),

    /*
    | Optional default path to sc-data-unpack-script export (sibling repo).
    | Override with --export= or SCANZ_EXPORT_LOCATIONS_PATH in .env
    */
    'export_locations_path' => env(
        'SCANZ_EXPORT_LOCATIONS_PATH',
        base_path('../sc-data-unpack-script/export/v1/locations.json'),
    ),
];
