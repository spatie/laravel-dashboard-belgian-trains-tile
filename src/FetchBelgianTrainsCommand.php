<?php

namespace Spatie\BelgianTrainsTile;

use Illuminate\Console\Command;

class FetchBelgianTrainsCommand extends Command
{
    protected $signature = 'dashboard:fetch-belgian-trains';

    protected $description = 'Fetch Belgian Trains Information';

    public function handle(IRailApi $iRail): void
    {
        $this->info('Fetching trainConnections from iRail...');

        $locale = config('dashboard.tiles.belgian_trains.locale') ?? 'nl';

        $store = TrainConnectionsStore::make();
        $previousConnections = collect($store->trainConnections());
        $configuredConnections = collect(config('dashboard.tiles.belgian_trains.connections') ?? []);

        $trainConnections = $configuredConnections
            ->map(function (array $connection) use ($locale, $iRail, $previousConnections, $configuredConnections) {
                $trains = $iRail->getConnectionsOrNull(
                    $connection['departure'],
                    $connection['destination'],
                    $locale,
                    $connection['label'] ?? null,
                );

                if ($trains === null) {
                    $previousConnection = $previousConnections->first(function (array $previous) use ($connection, $configuredConnections, $previousConnections) {
                        if (isset($previous['departure'], $previous['destination'])) {
                            return $previous['departure'] === $connection['departure']
                                && $previous['destination'] === $connection['destination'];
                        }

                        return $previous['label'] === $connection['label']
                            && $configuredConnections->where('label', $connection['label'])->count() === 1
                            && $previousConnections->where('label', $connection['label'])->count() === 1;
                    });

                    $trains = $previousConnection['trains'] ?? [];
                }

                return [
                    'departure' => $connection['departure'],
                    'destination' => $connection['destination'],
                    'label' => $connection['label'],
                    'trains' => $trains,
                ];
            })
            ->toArray();

        $store->setTrainConnections($trainConnections);

        $this->info('All done!');
    }
}
