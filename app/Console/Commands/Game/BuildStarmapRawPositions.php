<?php

declare(strict_types=1);

namespace App\Console\Commands\Game;

use App\Support\Starmap\StarmapRawPositionBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use JsonException;

class BuildStarmapRawPositions extends Command
{
    protected $signature = 'game:build-starmap-raw-positions
                            {--output= : Output bundle path (default: data/enrichment/starmap-raw-positions.json)}
                            {--url=https://api.star-citizen.wiki/api/locations/positions : Wiki positions API URL}';

    protected $description = 'Build raw starmap XYZ + jump connections bundle from Star Citizen Wiki positions API';

    public function handle(): int
    {
        $output = (string) ($this->option('output') ?: base_path('data/enrichment/starmap-raw-positions.json'));
        $url = (string) $this->option('url');

        $this->info("Fetching raw starmap positions from {$url}");

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'sc-scan-z-build-starmap-raw-positions/1.0',
        ])->timeout(120)->get($url);

        if (! $response->successful()) {
            $this->error("Wiki fetch failed: HTTP {$response->status()}");

            return self::FAILURE;
        }

        try {
            /** @var array{data?: list<array<string, mixed>>, connections?: list<array<string, mixed>>} $payload */
            $payload = $response->json();
        } catch (JsonException $exception) {
            $this->error('Wiki response is not valid JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        $entities = $payload['data'] ?? [];
        $connections = $payload['connections'] ?? [];

        if (! is_array($entities) || $entities === []) {
            $this->error('Wiki response did not include any entities.');

            return self::FAILURE;
        }

        $bundle = StarmapRawPositionBundle::fromWikiPayload($entities, is_array($connections) ? $connections : []);
        StarmapRawPositionBundle::writeFile($output, $bundle);

        $locationCount = count($bundle['locations']);
        $connectionCount = count($bundle['connections']);
        $this->info("Wrote {$locationCount} raw starmap positions and {$connectionCount} connections to {$output}");

        return self::SUCCESS;
    }
}
