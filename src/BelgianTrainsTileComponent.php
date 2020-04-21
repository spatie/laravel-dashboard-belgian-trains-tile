<?php

namespace Spatie\BelgianTrainsTile;

use Livewire\Component;

class BelgianTrainsTileComponent extends Component
{
    protected static $showTile = null;

    /** @var string */
    public $position;

    public function mount(string $position)
    {
        $this->position = $position;
    }

    public function render()
    {
        $showTile = isset(static::$showTile)
            ? (static::$showTile)
            : true;

        return view('dashboard-belgian-trains-tile::tile', [
            'showTile' => $showTile,
            'trainConnections' => TrainConnectionsStore::make()->trainConnections(),
        ]);
    }

    public static function showTile(callable $callable): void
    {
        static::$showTile = $callable;
    }
}
