<?php

namespace zhaneul\zglicko2;

use Carbon\CarbonImmutable;

class OutcomeBatch
{
    /**
     * @var array<string, ['entity' => RatedEntity, 'before' => ?RatedEntity, 'after' => ?RatedEntity, 'outcomes' => array<Outcome>]>
     */
    protected array $entities = [];

    public function __construct(
        protected CarbonImmutable $atGte,
        protected CarbonImmutable $atLt,
    ) {}

    public function atGte(): CarbonImmutable
    {
        return $this->atGte;
    }

    public function atLt(): CarbonImmutable
    {
        return $this->atLt;
    }

    /**
     * @return RatedEntity[]
     */
    public function entities(): array
    {
        return $this->entities;
    }

    public function entity(string|RatedEntity $entity): ?RatedEntity
    {
        return $entity instanceof RatedEntity
            ? ($this->entities[$entity->key()]['entity'] ?? null)
            : ($this->entities[$entity]['entity'] ?? null);
    }

    public function before(string|RatedEntity $entity): ?RatedEntity
    {
        return $entity instanceof RatedEntity
            ? ($this->entities[$entity->key()]['before'] ?? null)
            : ($this->entities[$entity]['before'] ?? null);
    }

    public function after(string|RatedEntity $entity): ?RatedEntity
    {
        return $entity instanceof RatedEntity
            ? ($this->entities[$entity->key()]['after'] ?? null)
            : ($this->entities[$entity]['after'] ?? null);
    }

    public function setEntity(RatedEntity $entity): RatedEntity
    {
        $this->entities[$entity->key()]['entity'] = $entity;
        return $entity;
    }

    /**
     * @return array<Outcome>
     */
    public function outcomes(?string $entityKey = null): array
    {
        return isset($entityKey)
            ? $this->entities[$entityKey]['outcomes'] ?? []
            : array_reduce($this->entities, function ($carry, $item) {
                return array_merge($carry, $item['outcomes']);
            }, []);
    }

    public function push(Outcome $outcome): bool
    {
        if ($outcome->occurredAt()->gte($this->atGte) && $outcome->occurredAt()->lt($this->atLt)) {
            if (! isset($this->entities[$outcome->for()->key()])) {
                $this->initEntity($outcome->for());
            }
            $this->entities[$outcome->for()->key()]['outcomes'] []= $outcome;
            return true;
        }
        return false;
    }

    public function snapshotBefores(): static
    {
        foreach ($this->entities as $key => $entry) {
            $this->entities[$key]['before'] = $entry['entity']?->clone();
        }
        return $this;
    }

    public function snapshotAfters(): static
    {
        foreach ($this->entities as $key => $entry) {
            $this->entities[$key]['after'] = $entry['entity']?->clone();
        }
        return $this;
    }

    /**
     * Loads new entities into the context of this batch.
     *
     * @param array<RatedEntity> $entities
     * @return $this
     */
    public function loadContext(array $entities): static
    {
        foreach ($entities as $entity) {
            if (! isset($this->entities[$entity->key()])) {
                $this->initEntity($entity);
            }
        }
        return $this;
    }

    protected function initEntity(RatedEntity $entity): static
    {
        if (! isset($this->entities[$entity->key()])) {
            $this->entities[$entity->key()] = [
                'entity' => $entity,
                'before' => null,
                'after' => null,
                'outcomes' => [],
            ];
        }
        return $this;
    }
}
