<?php

namespace zhaneul\zglicko2;

use Carbon\CarbonImmutable;

class RatedEntity
{
    protected float $initialMu;
    protected float $initialPhi;
    protected float $initialSigma;

    public function __construct(
        protected readonly string $key,
        protected float $mu = 0,
        protected float $phi = 2,
        protected float $sigma = 0.06,
    )
    {
        $this->initialMu = $mu;
        $this->initialPhi = $phi;
        $this->initialSigma = $sigma;
    }

    public static function scaleFromRating(
        string $key,
        float $rating,
        float $deviation,
        float $scaleMedian = 1500,
        float $scaleFactor = 400,
        float $sigma = Glicko2::DEFAULT_SIGMA,
    ): RatedEntity
    {
        return new RatedEntity(
            key: $key,
            mu: ($rating - $scaleMedian) / ($scaleFactor / log(10)),
            phi: $deviation / ($scaleFactor / log(10)),
            sigma: $sigma,
        );
    }

    /**
     * @param RatedEntity $entity
     * @return array ['rating' => rating, 'deviation' => deviation]
     */
    public static function scaleToRating(RatedEntity $entity, float $scaleMedian = 1500, float $scaleFactor = 400): array
    {
        return [
            'rating' => ($scaleFactor / log(10)) * $entity->mu() + $scaleMedian,
            'deviation' => ($scaleFactor / log(10)) * $entity->phi(),
        ];
    }

    public function clone(): static
    {
        $entity = new RatedEntity(key: $this->key, mu: $this->mu, phi: $this->phi, sigma: $this->sigma);
        $entity->initialMu = $this->initialMu;
        $entity->initialPhi = $this->initialPhi;
        $entity->initialSigma = $this->initialSigma;
        return $entity;
    }

    public function revertToInitial(): static
    {
        $this->mu = $this->initialMu;
        $this->phi = $this->initialPhi;
        $this->sigma = $this->initialSigma;
        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function mu(): float
    {
        return $this->mu;
    }

    public function phi(): float
    {
        return $this->phi;
    }

    public function sigma(): float
    {
        return $this->sigma;
    }

    public function update(float $muPrime, float $phiPrime, ?float $sigmaPrime = null): static
    {
        $this->mu = $muPrime;
        $this->phi = $phiPrime;
        $this->sigma = $sigmaPrime ?? $this->sigma;
        return $this;
    }

    public function vs(RatedEntity $against, float $score, CarbonImmutable $occurredAt): Outcome
    {
        return new Outcome(for: $this, against: $against, score: $score, occurredAt: $occurredAt);
    }

    public function diffMu(): float
    {
        return $this->mu - $this->initialMu;
    }

    public function diffPhi(): float
    {
        return $this->phi - $this->initialPhi;
    }

    public function diffSigma(): float
    {
        return $this->sigma - $this->initialSigma;
    }

    public function initialMu(): float
    {
        return $this->initialMu;
    }

    public function initialPhi(): float
    {
        return $this->initialPhi;
    }

    public function initialSigma(): float
    {
        return $this->initialSigma;
    }
}
