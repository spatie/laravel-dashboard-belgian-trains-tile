<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Spatie\BelgianTrainsTile\Exceptions\InvalidIRailResponse;
use Spatie\BelgianTrainsTile\TrainConnectionsStore;

function fakeIRailResponse(array $connections = []): void
{
    Http::swap(new Factory());
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
        ['departure' => 'Antwerpen-Centraal', 'destination' => 'Bruxelles-Midi', 'label' => 'Antwerp → Brussels', 'trains' => []],
    ]);

    Exceptions::assertReported(function (InvalidIRailResponse $exception) {
        return $exception->context()['label'] === 'Antwerp → Brussels'
            && $exception->context()['departure_station'] === 'Antwerpen-Centraal'
            && $exception->context()['destination_station'] === 'Bruxelles-Midi'
            && $exception->context()['status_code'] === 503;
    });
});

function trainDeparture(string $station): array
{
    return ['departure' => [
        'direction' => ['name' => $station],
        'time' => '1678900000',
        'platform' => '3',
        'canceled' => '0',
        'delay' => '0',
    ]];
}

it('preserves the last successful departures when fetching fails', function (string $failure) {
    Exceptions::fake();
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'Route'],
    ]);

    fakeIRailResponse([trainDeparture('B')]);
    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();
    $previous = TrainConnectionsStore::make()->trainConnections();

    Http::swap(new Factory());
    Http::fake(fn () => match ($failure) {
        'timeout' => Http::response('gateway timeout', 504),
        'unavailable' => Http::response('unavailable', 503),
        'rate limit' => Http::response('too many requests', 429),
        'invalid JSON' => Http::response('not JSON'),
        'missing connections' => Http::response(['timestamp' => '123']),
        'invalid connections' => Http::response(['connection' => 'invalid']),
        'malformed departures' => Http::response(['connection' => [['departure' => null]]]),
        'network failure' => Http::failedConnection(),
    });

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    expect(TrainConnectionsStore::make()->trainConnections())->toBe($previous);
    Exceptions::assertReported(InvalidIRailResponse::class);
})->with([
    'timeout', 'unavailable', 'rate limit', 'invalid JSON', 'missing connections',
    'invalid connections', 'malformed departures', 'network failure',
]);

it('updates successful routes while retaining failed routes after reordering and relabeling', function () {
    Exceptions::fake();
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'First'],
        ['departure' => 'C', 'destination' => 'D', 'label' => 'Second'],
    ]);
    fakeIRailResponse([trainDeparture('Old destination')]);
    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();
    $previous = TrainConnectionsStore::make()->trainConnections();

    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'C', 'destination' => 'D', 'label' => 'Renamed second'],
        ['departure' => 'A', 'destination' => 'B', 'label' => 'First'],
    ]);
    Http::swap(new Factory());
    Http::fake(fn ($request) => str_contains($request->url(), 'from=C')
        ? Http::response('gateway timeout', 504)
        : Http::response(['connection' => [trainDeparture('Fresh destination')]]));

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();
    $stored = TrainConnectionsStore::make()->trainConnections();

    expect($stored[0]['label'])->toBe('Renamed second')
        ->and($stored[0]['trains'])->toBe($previous[1]['trains'])
        ->and($stored[1]['trains'][0]['station'])->toBe('Fresh destination');
});

it('clears cached departures after a successful empty response', function () {
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'Route'],
    ]);
    fakeIRailResponse([trainDeparture('B')]);
    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    fakeIRailResponse();
    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    expect(TrainConnectionsStore::make()->trainConnections()[0]['trains'])->toBe([]);
});

it('does not reuse cached departures for a different route with the same label', function () {
    Exceptions::fake();
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'Route'],
    ]);
    fakeIRailResponse([trainDeparture('B')]);
    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'C', 'label' => 'Route'],
    ]);
    Http::swap(new Factory());
    Http::fake(fn () => Http::response('gateway timeout', 504));
    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    expect(TrainConnectionsStore::make()->trainConnections()[0]['trains'])->toBe([]);
});

it('preserves departures stored before route identities were introduced', function () {
    Exceptions::fake();
    config()->set('dashboard.tiles.belgian_trains.connections', [
        ['departure' => 'A', 'destination' => 'B', 'label' => 'Route'],
    ]);
    $trains = [['station' => 'B', 'time' => '1678900000', 'platform' => '3', 'canceled' => false, 'delay' => 0]];
    TrainConnectionsStore::make()->setTrainConnections([
        ['label' => 'Route', 'trains' => $trains],
    ]);
    Http::swap(new Factory());
    Http::fake(fn () => Http::response('gateway timeout', 504));

    $this->artisan('dashboard:fetch-belgian-trains')->assertSuccessful();

    expect(TrainConnectionsStore::make()->trainConnections()[0]['trains'])->toBe($trains);
});
