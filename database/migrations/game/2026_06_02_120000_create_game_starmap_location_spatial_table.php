<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_starmap_location_spatial', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('starmap_location_data_id')
                ->constrained('game_starmap_location_data')
                ->cascadeOnDelete();
            $table->string('coordinate_space', 64);
            $table->double('position_x');
            $table->double('position_y');
            $table->double('position_z');
            $table->string('source', 64)->default('sc-export-pipeline');
            $table->uuid('system_uuid')->nullable();
            $table->timestamps();

            $table->unique('starmap_location_data_id');
            $table->index(['position_x', 'position_y', 'position_z']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_starmap_location_spatial');
    }
};
