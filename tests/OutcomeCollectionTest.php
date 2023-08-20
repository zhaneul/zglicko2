<?php

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use zhaneul\zglicko2\Outcome;
use zhaneul\zglicko2\OutcomeCollection;
use zhaneul\zglicko2\RatedEntity;

class OutcomeCollectionTest extends TestCase
{
    public function testMakeBatches()
    {
        $collection = new OutcomeCollection();
        $collection->push(new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::make('2000-01-01 01:23:45'),
        ));
        $collection->push(new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::make('2000-01-03 02:34:27'),
        ));
        $collection->push(new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::make('2000-01-07 03:45:41'),
        ));
        $collection->push(new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::make('2000-01-11 17:31:22'),
        ));
        $collection->push(new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::make('2000-01-11 19:29:51'),
        ));
        $collection->push(new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::make('2000-02-01 23:59:59'),
        ));

        // 1. Batch by day.
        $batches = $collection->makeBatches(stepSize: 1, stepType: 'day');
        $this->assertCount(32, $batches);
        $this->assertCount(1, $batches[0]->entities());
        $this->assertCount(2, $batches[2]->entities());
        $this->assertCount(3, $batches[6]->entities());
        $this->assertCount(5, $batches[10]->entities());
        $this->assertCount(6, $batches[31]->entities());

        // 2. Batch by week.
        $batches = $collection->makeBatches(stepSize: 1, stepType: 'week');
        $this->assertCount(6, $batches);
        $this->assertCount(1, $batches[0]->entities());
        $this->assertCount(3, $batches[1]->entities());
        $this->assertCount(5, $batches[2]->entities());
        $this->assertCount(6, $batches[5]->entities());

        // 3. Batch by month.
        $batches = $collection->makeBatches(stepSize: 1, stepType: 'month');
        $this->assertCount(2, $batches);
        $this->assertCount(5, $batches[0]->entities());
        $this->assertCount(6, $batches[1]->entities());
    }
}
