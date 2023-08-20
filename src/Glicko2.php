<?php

namespace zhaneul\zglicko2;

use Carbon\CarbonImmutable;

class Glicko2
{
    const DEFAULT_EPSILON = 0.000001;
    const DEFAULT_SIGMA = 0.06;
    const DEFAULT_TAU = 0.06;

    protected OutcomeCollection $outcomes;
    /**
     * @var array<string, RatedEntity> $entities
     */
    protected array $entities = [];

    public function __construct(
        /**
         * @var bool $decayMonotonically If true, as $phi widens, $mu must decrease.
         */
        protected bool $decayMonotonically = true,
        protected float $monotonicDecayVelocity = 1,
        protected float $minSigma = 0,
        protected float $maxSigma = 2 * Glicko2::DEFAULT_SIGMA,
        protected float $tau = Glicko2::DEFAULT_TAU,
        protected float $epsilon = Glicko2::DEFAULT_EPSILON,
    )
    {
        $this->outcomes = new OutcomeCollection();
    }

    public function addOutcome(Outcome ...$outcome): static
    {
        foreach ($outcome as $o) {
            $this->outcomes->push($o);

            if (! isset($this->entities[$o->for()->key()])) {
                $this->entities[$o->for()->key()] = $o->for();
            }
        }
        return $this;
    }

    public function entity(string|RatedEntity $entity): ?RatedEntity
    {
        return $entity instanceof RatedEntity
            ? ($this->entities[$entity->key()] ?? null)
            : ($this->entities[$entity] ?? null);
    }

    /**
     * @param int $stepSize
     * @param string $stepType
     * @param CarbonImmutable|null $anchor
     * @return array<OutcomeBatch>
     */
    public function rate(int $stepSize, string $stepType, ?CarbonImmutable $anchor = null): array
    {
        foreach ($this->entities as $entity) {
            $entity->revertToInitial();
        }

        $batches = $this->outcomes->makeBatches(
            stepSize: $stepSize,
            stepType: $stepType,
            anchor: $anchor,
        );

        foreach ($batches as $batch) {
            $batch->snapshotBefores();
            foreach ($batch->entities() as $entry) {
                $entity = $entry['entity'];
                $outcomes = $entry['outcomes'];

                if (count($outcomes) > 0) {
                    $this->updateActiveEntity($entity, $outcomes);
                } else {
                    $this->updateInactiveEntity($entity);
                }
            }
            $batch->snapshotAfters();
        }

        return $batches;
    }

    public function updateActiveEntity(RatedEntity $entity, array $outcomes): RatedEntity
    {
        $v = $this->v($entity, $outcomes);
        $delta = $this->delta($entity, $outcomes);

        $sigmaPrime = $this->sigmaPrime(
            phi: $entity->phi(),
            sigma: $entity->sigma(),
            v: $v,
            delta: $delta,
            tau: $this->tau,
            epsilon: $this->epsilon,
        );
        if ($sigmaPrime < $this->minSigma) {
            $sigmaPrime = $this->minSigma;
        } elseif ($sigmaPrime > $this->maxSigma) {
            $sigmaPrime = $this->maxSigma;
        }

        $phiStar = pow(pow($entity->phi(), 2) + pow($sigmaPrime, 2), 0.5);
        $phiPrime = 1 / pow(1 / pow($phiStar, 2) + 1 / $v, 0.5);

        $muPrime = $entity->mu() + pow($phiPrime, 2) * array_reduce($outcomes, function ($carry, $item) use ($entity) {
                $g = $this->g(phiJ: $item->against()->phi());
                $E = $this->E(
                    mu: $entity->mu(),
                    muJ: $item->against()->mu(),
                    phiJ: $item->against()->phi(),
                );
                return $carry + ($g * ($item->score() - $E));
            }, 0);

        return $entity->update(
            muPrime: $muPrime,
            phiPrime: $phiPrime,
            sigmaPrime: $sigmaPrime,
        );
    }

    public function updateInactiveEntity(RatedEntity $entity): RatedEntity
    {
        $phiPrime = pow(pow($entity->phi(), 2) + pow($entity->sigma(), 2), 0.5);
        $muPrime = $this->decayMonotonically
            ? $entity->mu() + $this->monotonicDecayVelocity * ($entity->phi() - $phiPrime)
            : $entity->mu();

        return $entity->update(
            muPrime: $muPrime,
            phiPrime: $phiPrime,
        );
    }

    /**
     * @param RatedEntity $entity
     * @param array<Outcome> $outcomes
     * @return float
     */
    protected function delta(RatedEntity $entity, array $outcomes): float
    {
        $v = $this->v($entity, $outcomes);
        $sum = array_reduce($outcomes, function ($carry, $item) use ($entity) {
            $g = $this->g(phiJ: $item->against()->phi());
            $E = $this->E(
                mu: $entity->mu(),
                muJ: $item->against()->mu(),
                phiJ: $item->against()->phi(),
            );

            return $carry + ($g * ($item->score() - $E));
        }, 0);

        return $v * $sum;
    }

    protected function E(float $mu, float $muJ, float $phiJ): float
    {
        return 1 / (1 + exp(-$this->g($phiJ) * ($mu - $muJ)));
    }

    protected function g(float $phiJ): float
    {
        return 1 / pow(1 + 3 * pow($phiJ, 2) / pow(pi(), 2), 0.5);
    }

    protected function sigmaPrime(float $phi, float $sigma, float $v, float $delta, float $tau, float $epsilon): float
    {
        $A = $a = log(pow($sigma, 2));
        $f = fn ($x) => (exp($x) * (pow($delta, 2) - pow($phi, 2) - $v - exp($x))) / (2 * pow(pow($phi, 2) + $v + exp($x), 2)) - ($x - $a) / pow($tau, 2);

        if (pow($delta, 2) > pow($phi, 2) + $v) {
            $B = log(pow($delta, 2) - pow($phi, 2) - $v);
        } else {
            $k = 1;
            while ($f($a - $k * $tau) < 0) {
                $k++;
            }
            $B = $a - $k * $tau;
        }

        $fA = $f($A);
        $fB = $f($B);

        while (abs($B - $A) > $epsilon) {
            $C = $A + ($A - $B) * $fA / ($fB - $fA);
            $fC = $f($C);

            if ($fC * $fB < 0) {
                $A = $B;
                $fA = $fB;
            } else {
                $fA /= 2;
            }

            $B = $C;
            $fB = $fC;
        }

        return exp($A / 2);
    }

    /**
     * @param RatedEntity $entity
     * @param array<Outcome> $outcomes
     * @return float
     */
    protected function v(RatedEntity $entity, array $outcomes): float
    {
        $sum = array_reduce($outcomes, function ($carry, $item) use ($entity) {
            $g = $this->g(phiJ: $item->against()->phi());
            $E = $this->E(
                mu: $entity->mu(),
                muJ: $item->against()->mu(),
                phiJ: $item->against()->phi(),
            );

            return $carry + (pow($g, 2) * $E * (1 - $E));
        },0);

        return 1 / $sum;
    }
}
