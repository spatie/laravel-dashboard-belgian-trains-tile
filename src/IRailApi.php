<?php

namespace Spatie\BelgianTrainsTile;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\BelgianTrainsTile\Exceptions\InvalidIRailResponse;
use Throwable;

class IRailApi
{
    public function getConnections(
        string $departureStationName,
        string $destinationStationName,
        string $locale,
        ?string $label = null,
    ): array
    {
        $endpoint = "https://api.irail.be/connections?from={$departureStationName}&to={$destinationStationName}&format=json&lang={$locale}";

        $requestContext = [
            'label' => $label,
            'departure_station' => $departureStationName,
            'destination_station' => $destinationStationName,
            'locale' => $locale,
            'endpoint' => $endpoint,
        ];

        try {
            $response = Http::acceptJson()->get($endpoint);
        } catch (Throwable $exception) {
            $this->reportInvalidResponse('request failed', [
                ...$requestContext,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            $this->reportInvalidResponse('unexpected status code', [
                ...$requestContext,
                ...$this->responseContext(
                    $response->status(),
                    $response->body(),
                    $response->header('Content-Type'),
                ),
            ]);

            return [];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            $this->reportInvalidResponse('response body could not be decoded as JSON', [
                ...$requestContext,
                ...$this->responseContext(
                    $response->status(),
                    $response->body(),
                    $response->header('Content-Type'),
                ),
            ]);

            return [];
        }

        if (! array_key_exists('connection', $payload)) {
            $this->reportInvalidResponse('response payload is missing the connection key', [
                ...$requestContext,
                ...$this->responseContext(
                    $response->status(),
                    $response->body(),
                    $response->header('Content-Type'),
                ),
            ]);

            return [];
        }

        $connections = $payload['connection'];

        if (! is_array($connections)) {
            $this->reportInvalidResponse('connection payload is not an array', [
                ...$requestContext,
                ...$this->responseContext(
                    $response->status(),
                    $response->body(),
                    $response->header('Content-Type'),
                ),
                'connection_payload_type' => get_debug_type($connections),
            ]);

            return [];
        }

        return collect($connections)
            ->map(fn (mixed $connection, int $index) => $this->normalizeConnection($connection, $index, $requestContext))
            ->filter()
            ->values()
            ->toArray();
    }

    protected function normalizeConnection(mixed $connection, int $index, array $requestContext): ?array
    {
        if (! is_array($connection)) {
            $this->reportInvalidResponse('connection entry is not an array', [
                ...$requestContext,
                'connection_index' => $index,
                'connection_preview' => $this->previewValue($connection),
            ]);

            return null;
        }

        $departure = $connection['departure'] ?? null;

        if (! is_array($departure)) {
            $this->reportInvalidResponse('connection entry is missing a valid departure payload', [
                ...$requestContext,
                'connection_index' => $index,
                'connection_preview' => $this->previewValue($connection),
            ]);

            return null;
        }

        $station = data_get($departure, 'direction.name');
        $time = $departure['time'] ?? null;

        if ($station === null || $time === null) {
            $this->reportInvalidResponse('connection entry is missing required departure fields', [
                ...$requestContext,
                'connection_index' => $index,
                'connection_preview' => $this->previewValue($connection),
            ]);

            return null;
        }

        return [
            'station' => (string) $station,
            'time' => (string) $time,
            'platform' => (string) ($departure['platform'] ?? ''),
            'canceled' => (bool) ($departure['canceled'] ?? false),
            'delay' => (int) ($departure['delay'] ?? 0) / 60,
        ];
    }

    protected function responseContext(int $statusCode, string $body, ?string $contentType): array
    {
        return [
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'response_body_preview' => $this->previewValue($body),
        ];
    }

    protected function previewValue(mixed $value): string
    {
        if (is_string($value)) {
            return Str::limit($value, 500);
        }

        $encodedValue = json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($encodedValue === false) {
            return get_debug_type($value);
        }

        return Str::limit($encodedValue, 500);
    }

    protected function reportInvalidResponse(string $reason, array $context): void
    {
        report(new InvalidIRailResponse($reason, $context));
    }
}
