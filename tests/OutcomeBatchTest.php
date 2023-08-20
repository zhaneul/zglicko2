<?php

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use zhaneul\zglicko2\Outcome;
use zhaneul\zglicko2\OutcomeBatch;
use zhaneul\zglicko2\RatedEntity;

class OutcomeBatchTest extends TestCase
{
    public function testPushOk()
    {
        $outcome = new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::now(),
        );
        $batch = new OutcomeBatch(
            atGte: CarbonImmutable::now()->subDays(1),
            atLt: CarbonImmutable::now()->addDays(1),
        );
        $this->assertTrue($batch->push($outcome));
        $this->assertCount(1, $batch->outcomes());
        $this->assertCount(1, $batch->outcomes($outcome->for()->key()));
        $this->assertCount(0, $batch->outcomes($outcome->against()->key()));
        $this->assertCount(1, $batch->entities());
        $this->assertSame($outcome->for(), $batch->entity($outcome->for()->key()));
        $this->assertNull($batch->entity($outcome->against()->key()));
    }

    public function testPushOutOfBounds()
    {
        $outcome = new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::now(),
        );
        $batch = new OutcomeBatch(
            atGte: CarbonImmutable::now()->subDays(2),
            atLt: CarbonImmutable::now()->subDays(1),
        );
        $this->assertFalse($batch->push($outcome));
        $this->assertCount(0, $batch->outcomes());
        $this->assertCount(0, $batch->outcomes($outcome->for()->key()));
        $this->assertCount(0, $batch->outcomes($outcome->against()->key()));
        $this->assertCount(0, $batch->entities());
        $this->assertNull($batch->entity($outcome->for()->key()));
        $this->assertNull($batch->entity($outcome->against()->key()));
    }
}
