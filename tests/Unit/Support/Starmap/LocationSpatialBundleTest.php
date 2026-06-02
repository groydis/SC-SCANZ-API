<?php

declare(strict_types=1);

use App\Support\Starmap\LocationSpatialBundle;

it('builds a spatial bundle from export locations', function (): void {
    $bundle = LocationSpatialBundle::fromExportLocations([
        [
            'uuid' => 'C6535844-C615-494E-9DC1-C2D0C6E7190A',
            'systemUuid' => '34ff378f-faee-47bb-b5fe-f505e665c5ca',
            'spatial' => [
                'worldPosition' => ['x' => 1.5, 'y' => 2.5, 'z' => 0.0],
            ],
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000000099',
            'spatial' => null,
        ],
    ]);

    expect($bundle['coordinate_space'])->toBe('derived_system_v1')
        ->and($bundle['locations'])->toHaveKey('c6535844-c615-494e-9dc1-c2d0c6e7190a')
        ->and($bundle['locations']['c6535844-c615-494e-9dc1-c2d0c6e7190a']['x'])->toBe(1.5);
});
