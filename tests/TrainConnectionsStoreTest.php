<?php

use Spatie\BelgianTrainsTile\TrainConnectionsStore;

it('stores and retrieves train connections', function () {
    $connections = [
        ['label' => 'Antwerp → Brussels', 'trains' => [['station' => 'Bruxelles-Midi']]],
    ];

    TrainConnectionsStore::make()->setTrainConnections($connections);

    expect(TrainConnectionsStore::make()->trainConnections())->toBe($connections);
});

it('returns empty array when nothing stored', function () {
    expect(TrainConnectionsStore::make()->trainConnections())->toBe([]);
});

it('overwrites previously stored connections', function () {
    $store = TrainConnectionsStore::make();

    $store->setTrainConnections([['label' => 'first']]);
    $store->setTrainConnections([['label' => 'second']]);

    expect($store->trainConnections())->toBe([['label' => 'second']]);
});

it('returns self for chaining', function () {
    $store = TrainConnectionsStore::make();

    $result = $store->setTrainConnections([]);

    expect($result)->toBeInstanceOf(TrainConnectionsStore::class);
});
