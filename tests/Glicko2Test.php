<?php

use PHPUnit\Framework\TestCase;
use zhaneul\zglicko2\Glicko2;
use zhaneul\zglicko2\RatedEntity;

class Glicko2Test extends TestCase
{
    public function testScaling()
    {
        $entityA = RatedEntity::scaleFromRating(
            key: 'A',
            rating: 1500,
            deviation: 200,
        );
        $this->assertEquals(0, $entityA->mu());
        $this->assertGreaterThanOrEqual(1.1512, $entityA->phi());
        $this->assertLessThanOrEqual(1.1513, $entityA->phi());
        $this->assertEquals(0.06, $entityA->sigma());
        $this->assertEquals(1500, (int) round(RatedEntity::scaleToRating($entityA)['rating']));
        $this->assertEquals(200, (int) round(RatedEntity::scaleToRating($entityA)['deviation']));

        $entityB = RatedEntity::scaleFromRating(
            key: 'B',
            rating: 1400,
            deviation: 30,
        );
        $this->assertGreaterThanOrEqual(-0.5757, $entityB->mu());
        $this->assertLessThanOrEqual(-0.5756, $entityB->mu());
        $this->assertGreaterThanOrEqual(0.1726, $entityB->phi());
        $this->assertLessThanOrEqual(0.1727, $entityB->phi());
        $this->assertEquals(0.06, $entityB->sigma());
        $this->assertEquals(1400, (int) round(RatedEntity::scaleToRating($entityB)['rating']));
        $this->assertEquals(30, (int) round(RatedEntity::scaleToRating($entityB)['deviation']));

        $entityC = RatedEntity::scaleFromRating(
            key: 'C',
            rating: 1550,
            deviation: 100,
        );
        $this->assertGreaterThanOrEqual(0.2878, $entityC->mu());
        $this->assertLessThanOrEqual(0.2879, $entityC->mu());
        $this->assertGreaterThanOrEqual(0.5756, $entityC->phi());
        $this->assertLessThanOrEqual(0.5757, $entityC->phi());
        $this->assertEquals(0.06, $entityC->sigma());
        $this->assertEquals(1550, (int) round(RatedEntity::scaleToRating($entityC)['rating']));
        $this->assertEquals(100, (int) round(RatedEntity::scaleToRating($entityC)['deviation']));

        $entityD = RatedEntity::scaleFromRating(
            key: 'D',
            rating: 1700,
            deviation: 300,
        );
        $this->assertGreaterThanOrEqual(1.1512, $entityD->mu());
        $this->assertLessThanOrEqual(1.1513, $entityD->mu());
        $this->assertGreaterThanOrEqual(1.7269, $entityD->phi());
        $this->assertLessThanOrEqual(1.7270, $entityD->phi());
        $this->assertEquals(0.06, $entityD->sigma());
        $this->assertEquals(1700, (int) round(RatedEntity::scaleToRating($entityD)['rating']));
        $this->assertEquals(300, (int) round(RatedEntity::scaleToRating($entityD)['deviation']));
    }

    public function testSingleBatch()
    {
        $glicko = new Glicko2(tau: 0.5);

        $entityA = RatedEntity::scaleFromRating(
            key: 'A',
            rating: 1500,
            deviation: 200,
        );
        $entityB = RatedEntity::scaleFromRating(
            key: 'B',
            rating: 1400,
            deviation: 30,
        );
        $entityC = RatedEntity::scaleFromRating(
            key: 'C',
            rating: 1550,
            deviation: 100,
        );
        $entityD = RatedEntity::scaleFromRating(
            key: 'D',
            rating: 1700,
            deviation: 300,
        );

        $glicko->addOutcome(
            $entityA->vs($entityB, 1, Carbon\CarbonImmutable::now()),
            $entityA->vs($entityC, 0, Carbon\CarbonImmutable::now()),
            $entityA->vs($entityD, 0, Carbon\CarbonImmutable::now()),
        );
        $glicko->rate(1, 'week');

        $this->assertEquals(0, $entityA->initialMu());
        $this->assertGreaterThanOrEqual(1.1512, $entityA->initialPhi());
        $this->assertLessThanOrEqual(1.1513, $entityA->initialPhi());
        $this->assertSame(0.06, $entityA->initialSigma());

        $this->assertGreaterThanOrEqual(-0.2070, $entityA->mu());
        $this->assertLessThanOrEqual(-0.2069, $entityA->mu());
        $this->assertGreaterThanOrEqual(0.8721, $entityA->phi());
        $this->assertLessThanOrEqual(0.8722, $entityA->phi());
        $this->assertGreaterThanOrEqual(0.0599, $entityA->sigma());
        $this->assertLessThanOrEqual(0.0600, $entityA->sigma());
    }

    public function testBatchesWithNonMonotonicDecay()
    {
        $glicko = new Glicko2(decayMonotonically: false, tau: 0.5);

        $entityA = RatedEntity::scaleFromRating(
            key: 'A',
            rating: 1500,
            deviation: 200,
        );
        $entityB = RatedEntity::scaleFromRating(
            key: 'B',
            rating: 1400,
            deviation: 30,
        );
        $entityC = RatedEntity::scaleFromRating(
            key: 'C',
            rating: 1550,
            deviation: 100,
        );
        $entityD = RatedEntity::scaleFromRating(
            key: 'D',
            rating: 1700,
            deviation: 300,
        );

        $glicko->addOutcome(
            $entityA->vs($entityB, 1, Carbon\CarbonImmutable::now()),
            $entityA->vs($entityC, 0, Carbon\CarbonImmutable::now()),
            $entityA->vs($entityD, 0, Carbon\CarbonImmutable::now()),
            $entityB->vs($entityC, 0, Carbon\CarbonImmutable::now()->addDay()),
        );
        $batches = $glicko->rate(1, 'day');

        $this->assertEquals(0, $entityA->initialMu());
        $this->assertGreaterThanOrEqual(1.1512, $entityA->initialPhi());
        $this->assertLessThanOrEqual(1.1513, $entityA->initialPhi());
        $this->assertSame(0.06, $entityA->initialSigma());

        $this->assertGreaterThanOrEqual(-0.2070, $entityA->mu());
        $this->assertLessThanOrEqual(-0.2069, $entityA->mu());
        $this->assertGreaterThanOrEqual(0.8742, $entityA->phi());
        $this->assertLessThanOrEqual(0.8743, $entityA->phi());
        $this->assertGreaterThanOrEqual(0.0599, $entityA->sigma());
        $this->assertLessThanOrEqual(0.0600, $entityA->sigma());

        $batch1 = $batches[0];

        $this->assertEquals(0, $batch1->before($entityA)->mu());
        $this->assertGreaterThanOrEqual(1.1512, $batch1->before($entityA)->phi());
        $this->assertLessThanOrEqual(1.1513, $batch1->before($entityA)->phi());
        $this->assertSame(0.06, $batch1->before($entityA)->sigma());

        $this->assertGreaterThanOrEqual(-0.2070, $batch1->after($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2069, $batch1->after($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8721, $batch1->after($entityA)->phi());
        $this->assertLessThanOrEqual(0.8722, $batch1->after($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch1->after($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch1->after($entityA)->sigma());

        $batch2 = $batches[1];

        $this->assertGreaterThanOrEqual(-0.2070, $batch2->before($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2069, $batch2->before($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8721, $batch2->before($entityA)->phi());
        $this->assertLessThanOrEqual(0.8722, $batch2->before($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch2->before($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch2->before($entityA)->sigma());

        $this->assertGreaterThanOrEqual(-0.2070, $batch2->after($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2069, $batch2->after($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8742, $batch2->after($entityA)->phi());
        $this->assertLessThanOrEqual(0.8743, $batch2->after($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch2->after($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch2->after($entityA)->sigma());
    }

    public function testBatchesWithMonotonicDecayAndReEntry()
    {
        $glicko = new Glicko2(decayMonotonically: true, monotonicDecayVelocity: 0.71, tau: 0.5);

        $entityA = RatedEntity::scaleFromRating(
            key: 'A',
            rating: 1500,
            deviation: 200,
        );
        $entityB = RatedEntity::scaleFromRating(
            key: 'B',
            rating: 1400,
            deviation: 30,
        );
        $entityC = RatedEntity::scaleFromRating(
            key: 'C',
            rating: 1550,
            deviation: 100,
        );
        $entityD = RatedEntity::scaleFromRating(
            key: 'D',
            rating: 1700,
            deviation: 300,
        );

        $glicko->addOutcome(
            $entityA->vs($entityB, 1, Carbon\CarbonImmutable::now()),
            $entityA->vs($entityC, 0, Carbon\CarbonImmutable::now()),
            $entityA->vs($entityD, 0, Carbon\CarbonImmutable::now()),
            $entityB->vs($entityC, 0, Carbon\CarbonImmutable::now()->addDay()),
        );
        $batches = $glicko->rate(1, 'day');

        $this->assertEquals(0, $entityA->initialMu());
        $this->assertGreaterThanOrEqual(1.1512, $entityA->initialPhi());
        $this->assertLessThanOrEqual(1.1513, $entityA->initialPhi());
        $this->assertSame(0.06, $entityA->initialSigma());

        $this->assertGreaterThanOrEqual(-0.2085, $entityA->mu());
        $this->assertLessThanOrEqual(-0.2084, $entityA->mu());
        $this->assertGreaterThanOrEqual(0.8742, $entityA->phi());
        $this->assertLessThanOrEqual(0.8743, $entityA->phi());
        $this->assertGreaterThanOrEqual(0.0599, $entityA->sigma());
        $this->assertLessThanOrEqual(0.0600, $entityA->sigma());

        $batch1 = $batches[0];

        $this->assertEquals(0, $batch1->before($entityA)->mu());
        $this->assertGreaterThanOrEqual(1.1512, $batch1->before($entityA)->phi());
        $this->assertLessThanOrEqual(1.1513, $batch1->before($entityA)->phi());
        $this->assertSame(0.06, $batch1->before($entityA)->sigma());

        $this->assertGreaterThanOrEqual(-0.2070, $batch1->after($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2069, $batch1->after($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8721, $batch1->after($entityA)->phi());
        $this->assertLessThanOrEqual(0.8722, $batch1->after($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch1->after($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch1->after($entityA)->sigma());

        $batch2 = $batches[1];

        $this->assertGreaterThanOrEqual(-0.2070, $batch2->before($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2069, $batch2->before($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8721, $batch2->before($entityA)->phi());
        $this->assertLessThanOrEqual(0.8722, $batch2->before($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch2->before($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch2->before($entityA)->sigma());

        $this->assertGreaterThanOrEqual(-0.2085, $batch2->after($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2084, $batch2->after($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8742, $batch2->after($entityA)->phi());
        $this->assertLessThanOrEqual(0.8743, $batch2->after($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch2->after($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch2->after($entityA)->sigma());

        // Re-entry! Will things still work... or do we have persistent side effects?
        $batches = $glicko->rate(1, 'day');

        $this->assertGreaterThanOrEqual(-0.2085, $entityA->mu());
        $this->assertLessThanOrEqual(-0.2084, $entityA->mu());
        $this->assertGreaterThanOrEqual(0.8742, $entityA->phi());
        $this->assertLessThanOrEqual(0.8743, $entityA->phi());
        $this->assertGreaterThanOrEqual(0.0599, $entityA->sigma());
        $this->assertLessThanOrEqual(0.0600, $entityA->sigma());

        $batch1 = $batches[0];

        $this->assertEquals(0, $batch1->before($entityA)->mu());
        $this->assertGreaterThanOrEqual(1.1512, $batch1->before($entityA)->phi());
        $this->assertLessThanOrEqual(1.1513, $batch1->before($entityA)->phi());
        $this->assertSame(0.06, $batch1->before($entityA)->sigma());

        $this->assertGreaterThanOrEqual(-0.2070, $batch1->after($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2069, $batch1->after($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8721, $batch1->after($entityA)->phi());
        $this->assertLessThanOrEqual(0.8722, $batch1->after($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch1->after($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch1->after($entityA)->sigma());

        $batch2 = $batches[1];

        $this->assertGreaterThanOrEqual(-0.2070, $batch2->before($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2069, $batch2->before($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8721, $batch2->before($entityA)->phi());
        $this->assertLessThanOrEqual(0.8722, $batch2->before($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch2->before($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch2->before($entityA)->sigma());

        $this->assertGreaterThanOrEqual(-0.2085, $batch2->after($entityA)->mu());
        $this->assertLessThanOrEqual(-0.2084, $batch2->after($entityA)->mu());
        $this->assertGreaterThanOrEqual(0.8742, $batch2->after($entityA)->phi());
        $this->assertLessThanOrEqual(0.8743, $batch2->after($entityA)->phi());
        $this->assertGreaterThanOrEqual(0.0599, $batch2->after($entityA)->sigma());
        $this->assertLessThanOrEqual(0.0600, $batch2->after($entityA)->sigma());
    }
}
