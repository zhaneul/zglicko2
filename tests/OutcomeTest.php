<?php

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use zhaneul\zglicko2\Outcome;
use zhaneul\zglicko2\RatedEntity;

class OutcomeTest extends TestCase
{
    public function testConstructWithoutException(): void
    {
        $this->expectNotToPerformAssertions();
        new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: mt_rand() / mt_getrandmax(),
            occurredAt: CarbonImmutable::now(),
        );
    }

    public function testConstructWithRangeException(): void
    {
        $this->expectException(RangeException::class);
        $this->expectExceptionMessage('Outcome Score must be between 0 and 1 inclusive.');
        new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: 1.1,
            occurredAt: CarbonImmutable::now(),
        );

        $this->expectException(RangeException::class);
        $this->expectExceptionMessage('Outcome Score must be between 0 and 1 inclusive.');
        new Outcome(
            for: new RatedEntity(uniqid()),
            against: new RatedEntity(uniqid()),
            score: -0.1,
            occurredAt: CarbonImmutable::now(),
        );
    }
}
