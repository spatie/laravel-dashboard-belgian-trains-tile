<?php

namespace Spatie\BelgianTrainsTile;
use Illuminate\Console\Command;

class FetchBelgianTrainsCommand extends Command
{
    protected $signature = 'dashboard:fetch-belgian-trains';

    protected $description = 'Fetch Belgian Trains Information';

    public function handle(IRailApi $iRail)
    {
        $this->info('Fetching trainConnections from iRail...');

        $trainConnections = collect(config('services.train_connections'))
            ->map(function (array $connection) use ($iRail) {
                $trains = $iRail->getConnections($connection['departure'], $connection['destination']);

                return ['label' => $connection['label'], 'trains' => $trains];
            })
            ->toArray();

        TrainConnectionsStore::make()->setTrainConnections($trainConnections);

        $this->info('All done!');
    }
}
