<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Spatie\BelgianTrainsTile\Exceptions\InvalidIRailResponse;
use Spatie\BelgianTrainsTile\TrainConnectionsStore;

function fakeIRailResponse(array $connections = []): void
{
    Http::fake([
        'api.irail.be/*' => Http::response([
            'connection' => $connections,
        ]),
    ]);
}

it('fetches connections and stores them', function () {
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'Antwerpen-Centraal', 'destination' => 'Bruxelles-Midi', 'label' => 'Antwerp → Brussels'],
    ]);

    fakeIRailResponse([
        [
            'departure' => [
                'direction' => ['name' => 'Bruxelles-Midi'],
                'time' => '1678900000',
                'platform' => '3',
                'canceled' => '0',
                'delay' => '0',
            ],
        ],
    ]);

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    $stored = TrainConnectionsStore::make()->trainConnections();

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['label'])->toBe('Antwerp → Brussels')
        ->and($stored[0]['trains'])->toHaveCount(1);
});

it('uses configured locale in API call', function () {
    config()->set('dashboard.tiles.belgian_trains.locale', 'fr');
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'Test'],
    ]);

    fakeIRailResponse();

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'lang=fr');
    });
});

it('defaults to Dutch locale', function () {
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'Test'],
    ]);

    fakeIRailResponse();

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'lang=nl');
    });
});

it('handles multiple configured connections', function () {
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'Route 1'],
        ['departure' => 'C', 'destination' => 'D', 'label' => 'Route 2'],
    ]);

    fakeIRailResponse();

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    $stored = TrainConnectionsStore::make()->trainConnections();

    expect($stored)->toHaveCount(2)
        ->and($stored[0]['label'])->toBe('Route 1')
        ->and($stored[1]['label'])->toBe('Route 2');
});

it('handles empty connections config', function () {
    config()->set('dashboard.tiles.belgian_trains.connections', []);

    fakeIRailResponse();

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    expect(TrainConnectionsStore::make()->trainConnections())->toBe([]);
});

it('reports invalid iRail responses and keeps the command successful', function () {
    Exceptions::fake();

    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'Antwerpen-Centraal', 'destination' => 'Bruxelles-Midi', 'label' => 'Antwerp → Brussels'],
    ]);

    Http::fake([
        'api.irail.be/*' => Http::response('temporarily unavailable', 503),
    ]);

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    expect(TrainConnectionsStore::make()->trainConnections())->toBe([
        ['label' => 'Antwerp → Brussels', 'trains' => []],
    ]);

    Exceptions::assertReported(function (InvalidIRailResponse $exception) {
        return $exception->context()['label'] === 'Antwerp → Brussels'
            && $exception->context()['departure_station'] === 'Antwerpen-Centraal'
            && $exception->context()['destination_station'] === 'Bruxelles-Midi'
            && $exception->context()['status_code'] === 503;
    });
});
