<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PredictedMaxHeartRatePreviewCalculator;
use PHPUnit\Framework\TestCase;

final class PredictedMaxHeartRatePreviewCalculatorTest extends TestCase
{
    public function test_tanaka_reference_values_and_no_persistence(): void
    {
        $calculator = new PredictedMaxHeartRatePreviewCalculator();
        $this->assertSame(187, $calculator->evaluate(30)['estimated_bpm']);
        $this->assertSame(180, $calculator->evaluate(40)['estimated_bpm']);
        $this->assertSame(166, $calculator->evaluate(60)['estimated_bpm']);
        $this->assertSame('preview_available', $calculator->evaluate(30)['status']);
        $this->assertFalse($calculator->evaluate(30)['professionally_approved']);
        $this->assertFalse($calculator->evaluate(30)['persisted']);
    }

    public function test_no_adult_estimate_for_missing_or_out_of_scope_age(): void
    {
        $calculator = new PredictedMaxHeartRatePreviewCalculator();
        foreach ([null, -1, 17, 101] as $age) {
            $r = $calculator->evaluate($age);
            $this->assertNotSame('preview_available', $r['status']);
            $this->assertNull($r['estimated_bpm']);
        }
    }
}
