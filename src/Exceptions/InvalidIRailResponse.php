<?php

namespace Spatie\BelgianTrainsTile\Exceptions;

use Exception;

class InvalidIRailResponse extends Exception
{
    public function __construct(
        protected string $reason,
        protected array $reportContext = [],
    ) {
        parent::__construct("Received invalid iRail response: {$reason}.");
    }

    public function context(): array
    {
        return $this->reportContext;
    }
}
