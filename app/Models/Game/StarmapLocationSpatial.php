<?php

declare(strict_types=1);

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarmapLocationSpatial extends Model
{
    protected $table = 'game_starmap_location_spatial';

    protected $fillable = [
        'starmap_location_data_id',
        'coordinate_space',
        'position_x',
        'position_y',
        'position_z',
        'source',
        'system_uuid',
    ];

    protected $casts = [
        'starmap_location_data_id' => 'integer',
        'position_x' => 'float',
        'position_y' => 'float',
        'position_z' => 'float',
    ];

    public function locationData(): BelongsTo
    {
        return $this->belongsTo(StarmapLocationData::class, 'starmap_location_data_id');
    }
}
