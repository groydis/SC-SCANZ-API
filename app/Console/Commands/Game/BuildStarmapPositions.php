<?php

declare(strict_types=1);

namespace App\Console\Commands\Game;

use App\Support\Starmap\StarmapPositionBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;

class BuildStarmapPositions extends Command
{
    protected $signature = 'game:build-starmap-positions
                            {--output=starmap_positions.json : Relative path on the scunpacked disk}';

    protected $description = 'Build starmap_positions.json from starmap.json + location spatial enrichment';

    public function handle(): int
    {
        try {
            $payload = StarmapPositionBundle::build();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $relativePath = (string) $this->option('output');

        try {
            Storage::disk('scunpacked')->put(
                $relativePath,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            );
        } catch (JsonException $exception) {
            $this->error('Failed to encode starmap positions JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Wrote %d entities and %d connections to scunpacked:%s',
            count($payload['entities']),
            count($payload['connections']),
            $relativePath,
        ));

        return self::SUCCESS;
    }
}
