<?php

declare(strict_types=1);

use App\Support\Starmap\StarmapLocationSlugBuilder;

it('assigns bare slug to highest-priority type when names collide', function (): void {
    $builder = new StarmapLocationSlugBuilder;

    $slugs = $builder->build([
        'sys-uuid' => [
            'UUID' => 'sys-uuid',
            'Name' => 'Stanton',
            'Type' => ['Name' => 'SolarSystem'],
        ],
        'star-uuid' => [
            'UUID' => 'star-uuid',
            'Name' => 'Stanton',
            'Type' => ['Name' => 'Star'],
        ],
        'planet-uuid' => [
            'UUID' => 'planet-uuid',
            'Name' => 'ArcCorp',
            'Type' => ['Name' => 'Planet'],
        ],
    ]);

    expect($slugs['planet-uuid'])->toBe('arccorp')
        ->and($slugs['star-uuid'])->toBe('stanton')
        ->and($slugs['sys-uuid'])->toBe('stanton-solarsystem');
});

it('keeps unique names without numeric suffixes', function (): void {
    $builder = new StarmapLocationSlugBuilder;

    $slugs = $builder->build([
        'a' => ['UUID' => 'a', 'Name' => 'Area18', 'Type' => ['Name' => 'LandingZone']],
        'b' => ['UUID' => 'b', 'Name' => 'Lorville', 'Type' => ['Name' => 'LandingZone']],
    ]);

    expect($slugs)->toBe(['a' => 'area18', 'b' => 'lorville']);
});
