<?php

declare(strict_types=1);

namespace App\Console\Commands\Game;

use App\Models\Game\GameVersion;
use App\Models\Game\StarmapLocation;
use App\Models\Game\StarmapLocationData;
use App\Support\Starmap\StarmapLocationSlugBuilder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;

class RepairStarmapSlugs extends Command implements PromptsForMissingInput
{
    protected $signature = 'game:repair-starmap-slugs
                            {version : Game version code used to read starmap Type.Name from imported data}
                            {--dry-run : Show changes without writing}';

    protected $description = 'Reassign location slugs using type-based names (arccorp, stanton, stanton-solarsystem) instead of -2 suffixes';

    public function handle(StarmapLocationSlugBuilder $builder): int
    {
        $versionCode = (string) $this->argument('version');
        $dryRun = (bool) $this->option('dry-run');

        $gameVersion = GameVersion::query()->where('code', $versionCode)->first();

        if ($gameVersion === null) {
            $this->error(sprintf('Game version "%s" does not exist.', $versionCode));

            return self::FAILURE;
        }

        $rows = StarmapLocationData::query()
            ->where('game_version_id', $gameVersion->id)
            ->with('location:id,uuid,slug')
            ->get(['id', 'starmap_location_id', 'data']);

        if ($rows->isEmpty()) {
            $this->warn('No starmap location data for this version. Run game:import-starmap first.');

            return self::SUCCESS;
        }

        $entries = [];

        foreach ($rows as $row) {
            $uuid = $row->location?->uuid;

            if ($uuid === null) {
                continue;
            }

            $payload = $row->data;

            $entries[$uuid] = is_array($payload)
                ? $payload
                : (is_object($payload) && method_exists($payload, 'all') ? $payload->all() : []);
        }

        $slugMap = $builder->build($entries);
        $changes = 0;

        foreach ($rows as $row) {
            $uuid = $row->location?->uuid;
            $old = $row->location?->slug;
            $new = $uuid !== null ? ($slugMap[$uuid] ?? null) : null;

            if ($uuid === null || $new === null || $old === $new) {
                continue;
            }

            $changes++;
            $this->line(sprintf('%s  %s → %s', $uuid, $old ?? '(null)', $new));
        }

        if ($changes === 0) {
            $this->info('No slug changes needed.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("Dry run: {$changes} location(s) would be updated.");

            return self::SUCCESS;
        }

        DB::transaction(static function () use ($slugMap): void {
            foreach (StarmapLocation::query()->pluck('id', 'uuid') as $uuid => $id) {
                StarmapLocation::query()->whereKey($id)->update(['slug' => 'tmp-'.$id]);
            }

            foreach ($slugMap as $uuid => $slug) {
                StarmapLocation::query()->where('uuid', $uuid)->update(['slug' => $slug]);
            }
        });

        $this->info("Updated {$changes} location slug(s).");

        return self::SUCCESS;
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'version' => function (): string {
                $options = GameVersion::query()
                    ->orderByDesc('released_at')
                    ->orderBy('code')
                    ->pluck('code', 'code')
                    ->toArray();

                if ($options === []) {
                    $this->error('No game versions exist.');

                    return '';
                }

                return select(label: 'Game version', options: $options);
            },
        ];
    }
}
