<?php

declare(strict_types=1);

use App\Models\Game\GameVersion;
use App\Models\Game\StarmapLocation;
use App\Models\Game\StarmapLocationData;
use App\Models\Game\StarmapLocationSpatial;
use App\Support\Starmap\LocationSpatialBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports spatial bundle for a game version', function (): void {
    $version = GameVersion::factory()->create(['code' => '4.2.0-LIVE', 'is_default' => true]);
    $location = StarmapLocation::factory()->create([
        'uuid' => 'c6535844-c615-494e-9dc1-c2d0c6e7190a',
    ]);
    StarmapLocationData::factory()
        ->for($location, 'location')
        ->for($version, 'gameVersion')
        ->create(['name' => 'Area18', 'system' => 'Stanton', 'type_name' => 'LandingZone']);

    $bundlePath = storage_path('framework/testing/location-spatial.json');
    LocationSpatialBundle::writeFile($bundlePath, [
        'version' => 1,
        'coordinate_space' => 'derived_system_v1',
        'locations' => [
            'c6535844-c615-494e-9dc1-c2d0c6e7190a' => [
                'x' => 100.0,
                'y' => 200.0,
                'z' => 0.0,
                'source' => 'test',
            ],
        ],
    ]);

    $this->artisan('game:import-location-spatial', [
        'version' => $version->code,
        '--path' => $bundlePath,
    ])->assertExitCode(0);

    $spatial = StarmapLocationSpatial::query()->first();

    expect($spatial)->not->toBeNull()
        ->and($spatial->coordinate_space)->toBe('derived_system_v1')
        ->and($spatial->position_x)->toBe(100.0)
        ->and($spatial->source)->toBe('test');
});

it('exposes spatial on location api show', function (): void {
    $version = GameVersion::factory()->create(['code' => '4.2.0-LIVE', 'is_default' => true]);
    $location = StarmapLocation::factory()->create([
        'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'slug' => 'test-poi',
    ]);
    $locationData = StarmapLocationData::factory()
        ->for($location, 'location')
        ->for($version, 'gameVersion')
        ->create([
            'name' => 'Test POI',
            'system' => 'Stanton',
            'type_name' => 'Outpost',
            'data' => ['Type' => ['Classification' => 'Outpost']],
        ]);

    StarmapLocationSpatial::query()->create([
        'starmap_location_data_id' => $locationData->id,
        'coordinate_space' => 'derived_system_v1',
        'position_x' => 10.0,
        'position_y' => 20.0,
        'position_z' => 30.0,
        'source' => 'test',
    ]);

    $response = $this->getJson('/api/locations/test-poi');

    $response->assertOk()
        ->assertJsonPath('data.spatial.coordinate_space', 'derived_system_v1')
        ->assertJsonPath('data.spatial.world_position.x', 10.0)
        ->assertJsonPath('data.spatial.world_position.y', 20.0)
        ->assertJsonPath('data.spatial.world_position.z', 30.0);
});
