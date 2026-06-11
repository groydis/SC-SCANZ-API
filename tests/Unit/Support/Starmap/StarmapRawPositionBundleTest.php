<?php

declare(strict_types=1);

use App\Support\Starmap\StarmapRawPositionBundle;

describe('StarmapRawPositionBundle', function () {
    it('stores and loads wiki connections from bundle file', function () {
        $path = sys_get_temp_dir().'/starmap-raw-positions-test-'.uniqid('', true).'.json';

        $bundle = StarmapRawPositionBundle::fromWikiPayload(
            [
                [
                    'uuid' => 'aaa-bbb',
                    'x' => 1.0,
                    'y' => 2.0,
                    'z' => 3.0,
                ],
            ],
            [
                [
                    'entry_uuid' => '111-222',
                    'exit_uuid' => '333-444',
                    'entry_system' => 'stanton',
                    'exit_system' => 'pyro',
                    'fuel_cost' => 240000,
                ],
            ],
        );

        StarmapRawPositionBundle::writeFile($path, $bundle);

        expect(StarmapRawPositionBundle::loadConnections($path))->toBe([
            [
                'entry_uuid' => '111-222',
                'exit_uuid' => '333-444',
                'entry_system' => 'stanton',
                'exit_system' => 'pyro',
                'fuel_cost' => 240000,
                'size_class' => 'unknown',
            ],
        ]);

        @unlink($path);
    });

    it('returns empty connections when source bundle omits them', function () {
        $path = sys_get_temp_dir().'/starmap-raw-positions-test-'.uniqid('', true).'.json';

        StarmapRawPositionBundle::writeFile($path, [
            'version' => 1,
            'coordinate_space' => StarmapRawPositionBundle::COORDINATE_SPACE,
            'locations' => [
                'aaa-bbb' => ['x' => 1.0, 'y' => 2.0, 'z' => 3.0],
            ],
        ]);

        expect(StarmapRawPositionBundle::loadConnections($path))->toBe([]);

        @unlink($path);
    });

    it('normalizes wiki connection field names without inventing extras', function () {
        $connections = StarmapRawPositionBundle::normalizeWikiConnections([
            [
                'entry_uuid' => '63105afc-38df-48cc-b570-8eba703a57d7',
                'exit_uuid' => '80bac534-3e84-4a2d-97c2-3edefa2d5bef',
                'entry_system' => 'nyx',
                'exit_system' => 'pyro',
                'fuel_cost' => 240000,
            ],
        ]);

        expect($connections)->toHaveCount(1)
            ->and($connections[0]['entry_uuid'])->toBe('63105afc-38df-48cc-b570-8eba703a57d7')
            ->and($connections[0]['exit_system'])->toBe('pyro')
            ->and($connections[0]['size_class'])->toBe('unknown');
    });
});
