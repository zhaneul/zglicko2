<?php

namespace zhaneul\zglicko2;

use Carbon\CarbonImmutable;

class OutcomeCollection
{
    /**
     * @var array<Outcome> $outcomes
     */
    protected array $outcomes = [];
    protected CarbonImmutable $minOccurredAt;

    public function __construct() {}

    /**
     * @return array<Outcome>
     */
    public function outcomes(): array
    {
        return $this->outcomes;
    }

    /**
     * Transforms outcomes into a collection of OutcomeBatches, with empty OutcomeBatches inserted for any
     * gaps in the time series.
     *
     * @param int $stepSize
     * @param string $stepType ∈ {'day', 'week', 'month'}.
     * @param CarbonImmutable|null $anchor Defaults to $this->minOccurredAt if not provided.
     * @return array<OutcomeBatch> In ascending order of atGte().
     */
    public function makeBatches(int $stepSize, string $stepType, ?CarbonImmutable $anchor = null): array
    {
        /**
         * @var array<string, RatedEntity> $entities
         */
        $entities = [];
        $anchor = match ($stepType) {
            'day' => $anchor ?? $this->minOccurredAt?->startOfDay(),
            'week' => $anchor ?? $this->minOccurredAt?->startOfWeek(),
            'month' => $anchor ?? $this->minOccurredAt?->startOfMonth(),
            default => throw new \InvalidArgumentException("Invalid stepType value of {$stepType}."),
        };

        if (! isset($anchor)) {
            return [];
        }

        $batches = [
            ($batch = new OutcomeBatch($anchor, $anchor->add($stepSize, $stepType))),
        ];

        foreach ($this->outcomes as $outcome) {
            $entities[$outcome->for()->key()] = $outcome->for();
            while (! $batch->push($outcome)) {
                $batch = new OutcomeBatch(
                    atGte: $batch->atGte()->add($stepSize, $stepType),
                    atLt: $batch->atLt()->add($stepSize, $stepType),
                );
                $batch->loadContext($entities);
                $batches []= $batch;
            }
        }

        usort($batches, fn($a, $b) => $a->atGte()->timestamp <=> $b->atGte()->timestamp);
        return $batches;
    }

    public function push(Outcome $outcome): static
    {
        $this->outcomes []= $outcome;
        if (! isset($this->minOccurredAt) || $outcome->occurredAt()->lt($this->minOccurredAt)) {
            $this->minOccurredAt = $outcome->occurredAt();
        }
        return $this;
    }
}
