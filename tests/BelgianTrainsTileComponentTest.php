<?php

use Livewire\Livewire;
use Spatie\BelgianTrainsTile\BelgianTrainsTileComponent;
use Spatie\BelgianTrainsTile\TrainConnectionsStore;

afterEach(function () {
    BelgianTrainsTileComponent::showTile(fn () => true);
});

it('renders tile with train connection data', function () {
    TrainConnectionsStore::make()->setTrainConnections([
        [
            'label' => 'Antwerp → Brussels',
            'trains' => [
                [
                    'station' => 'Bruxelles-Midi',
                    'time' => '1678900000',
                    'platform' => '3',
                    'canceled' => false,
                    'delay' => 0,
                ],
            ],
        ],
    ]);

    Livewire::test(BelgianTrainsTileComponent::class, ['position' => 'a1:a2'])
        ->assertSee('Antwerp → Brussels')
        ->assertSee('Bruxelles-Midi');
});

it('renders empty when no connections stored', function () {
    Livewire::test(BelgianTrainsTileComponent::class, ['position' => 'a1:a2'])
        ->assertDontSee('Bruxelles-Midi');
});

it('uses refresh interval from config', function () {
    config()->set('dashboard.tiles.belgian_trains.refresh_interval_in_seconds', 120);

    Livewire::test(BelgianTrainsTileComponent::class, ['position' => 'a1:a2'])
        ->assertSee('120');
});

it('hides tile when showTile returns false', function () {
    BelgianTrainsTileComponent::showTile(fn () => false);

    Livewire::test(BelgianTrainsTileComponent::class, ['position' => 'a1:a2'])
        ->assertDontSee('Antwerp');
});
