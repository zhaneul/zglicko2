<?php

namespace zhaneul\zglicko2;

use Carbon\CarbonImmutable;
use RangeException;

class Outcome
{
    public function __construct(
        protected readonly RatedEntity $for,
        protected readonly RatedEntity $against,
        protected readonly float       $score,
        protected CarbonImmutable      $occurredAt,
    ) {
        if ($score < 0 || $score > 1) {
            throw new RangeException('Outcome Score must be between 0 and 1 inclusive.');
        }
    }

    public function for(): RatedEntity
    {
        return $this->for;
    }

    public function against(): RatedEntity
    {
        return $this->against;
    }

    public function score(): float
    {
        return $this->score;
    }

    public function occurredAt(): CarbonImmutable
    {
        return $this->occurredAt;
    }
}
