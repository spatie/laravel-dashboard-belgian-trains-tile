<?php

namespace Spatie\BelgianTrainsTile;

use Illuminate\Contracts\View\View;
use Spatie\Dashboard\Components\BaseTileComponent;

class BelgianTrainsTileComponent extends BaseTileComponent
{
    protected static $showTile = null;

    public function render(): View
    {
        $showTile = isset(static::$showTile)
            ? (static::$showTile)()
            : true;

        return view('dashboard-belgian-trains-tile::tile', [
            'showTile' => $showTile,
            'trainConnections' => TrainConnectionsStore::make()->trainConnections(),
            'refreshIntervalInSeconds' => config('dashboard.tiles.belgian_trains.refresh_interval_in_seconds') ?? 60,
        ]);
    }

    public static function showTile(callable $callable): void
    {
        static::$showTile = $callable;
    }
}
