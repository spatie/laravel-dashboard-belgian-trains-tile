<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Spatie\BelgianTrainsTile\Exceptions\InvalidIRailResponse;
use Spatie\BelgianTrainsTile\IRailApi;

beforeEach(function () {
    $this->api = new IRailApi();
});

it('parses connections correctly', function () {
    Http::fake([
        'api.irail.be/*' => Http::response([
            'connection' => [
                [
                    'departure' => [
                        'direction' => ['name' => 'Bruxelles-Midi'],
                        'time' => '1678900000',
                        'platform' => '3',
                        'canceled' => '0',
                        'delay' => '300',
                    ],
                ],
            ],
        ]),
    ]);

    $connections = $this->api->getConnections('Antwerpen-Centraal', 'Bruxelles-Midi', 'nl');

    expect($connections)->toHaveCount(1)
        ->and($connections[0])->toMatchArray([
            'station' => 'Bruxelles-Midi',
            'time' => '1678900000',
            'platform' => '3',
            'canceled' => false,
            'delay' => 5,
        ]);
});

it('sends correct query parameters', function () {
    Http::fake([
        'api.irail.be/*' => Http::response(['connection' => []]),
    ]);

    $this->api->getConnections('Gent-Sint-Pieters', 'Brugge', 'fr');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'from=Gent-Sint-Pieters')
            && str_contains($request->url(), 'to=Brugge')
            && str_contains($request->url(), 'lang=fr')
            && str_contains($request->url(), 'format=json');
    });
});

it('returns empty array for empty connection list', function () {
    Http::fake([
        'api.irail.be/*' => Http::response(['connection' => []]),
    ]);

    $connections = $this->api->getConnections('A', 'B', 'nl');

    expect($connections)->toBe([]);
});

it('returns empty array when connection key is missing', function () {
    Exceptions::fake();

    Http::fake([
        'api.irail.be/*' => Http::response(['timestamp' => '123']),
    ]);

    $connections = $this->api->getConnections('A', 'B', 'nl');

    expect($connections)->toBe([]);

    Exceptions::assertReported(function (InvalidIRailResponse $exception) {
        return $exception->context()['departure_station'] === 'A'
            && $exception->context()['destination_station'] === 'B';
    });
});

it('converts delay from seconds to minutes', function () {
    Http::fake([
        'api.irail.be/*' => Http::response([
            'connection' => [
                [
                    'departure' => [
                        'direction' => ['name' => 'Leuven'],
                        'time' => '1678900000',
                        'platform' => '1',
                        'canceled' => '0',
                        'delay' => '720',
                    ],
                ],
            ],
        ]),
    ]);

    $connections = $this->api->getConnections('A', 'B', 'nl');

    expect($connections[0]['delay'])->toBe(12);
});

it('returns an empty array and reports when the response body is invalid', function () {
    Exceptions::fake();

    Http::fake([
        'api.irail.be/*' => Http::response('not valid json', 200),
    ]);

    $connections = $this->api->getConnections('A', 'B', 'nl');

    expect($connections)->toBe([]);

    Exceptions::assertReported(function (InvalidIRailResponse $exception) {
        return $exception->context()['departure_station'] === 'A'
            && $exception->context()['destination_station'] === 'B'
            && $exception->context()['status_code'] === 200;
    });
});

it('skips malformed connection entries and reports them', function () {
    Exceptions::fake();

    Http::fake([
        'api.irail.be/*' => Http::response([
            'connection' => [
                [
                    'departure' => null,
                ],
                [
                    'departure' => [
                        'direction' => ['name' => 'Leuven'],
                        'time' => '1678900000',
                        'platform' => '1',
                        'canceled' => '0',
                        'delay' => '0',
                    ],
                ],
            ],
        ]),
    ]);

    $connections = $this->api->getConnections('A', 'B', 'nl', 'Test route');

    expect($connections)->toHaveCount(1)
        ->and($connections[0]['station'])->toBe('Leuven');

    Exceptions::assertReported(function (InvalidIRailResponse $exception) {
        return $exception->context()['label'] === 'Test route'
            && $exception->context()['connection_index'] === 0;
    });
});
