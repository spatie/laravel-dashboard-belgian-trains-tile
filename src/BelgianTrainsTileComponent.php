<?php

namespace Spatie\BelgianTrainsTile;

use Livewire\Component;

class BelgianTrainsTileComponent extends Component
{
    /** @var string */
    public $position;

    public function mount(string $position)
    {
        $this->position = $position;
    }

    public function render()
    {
        return view('dashboard-belgian-trains-tile::tile', [
            'showTile' => true,
            'trainConnections' => TrainConnectionsStore::make()->trainConnections(),
        ]);
    }
}
