<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Run2400MeterPreviewCalculator;
use PHPUnit\Framework\TestCase;

final class Run2400MeterPreviewCalculatorTest extends TestCase
{
    public function test_reference_values_for_2400m_and_one_and_half_miles(): void
    {
        $calculator = new Run2400MeterPreviewCalculator();
        foreach ([
            [2400, 720, 43.75],
            [2414, 720, 43.75],
            [2400, 900, 35.7],
            [2400, 600, 51.8],
            [2400, 630, 49.5],
        ] as [$distance, $seconds, $expected]) {
            $result = $calculator->evaluate('run_2400m', $distance, $seconds, 35);
            $this->assertSame('preview_available', $result['status']);
            $this->assertSame($expected, $result['estimated_vo2_ml_kg_min']);
            $this->assertFalse($result['professionally_approved']);
            $this->assertFalse($result['persisted']);
            $this->assertNull($result['classification']);
        }
    }

    public function test_protocol_and_age_must_be_valid(): void
    {
        $c = new Run2400MeterPreviewCalculator();
        foreach ([
            [null, 35, 'protocol_not_selected'],
            ['cooper_12_min', 35, 'other_protocol'],
            ['run_2400m', null, 'missing_age'],
            ['run_2400m', 17, 'outside_review_scope'],
            ['run_2400m', 101, 'outside_review_scope'],
        ] as [$protocol, $age, $status]) {
            $r = $c->evaluate($protocol, 2400, 720, $age);
            $this->assertSame($status, $r['status']);
            $this->assertNull($r['estimated_vo2_ml_kg_min']);
        }
    }

    public function test_missing_invalid_and_wrong_distance_or_time_never_create_result(): void
    {
        $c = new Run2400MeterPreviewCalculator();
        foreach ([
            [null, 720, 'missing_distance'],
            [2500, 720, 'invalid_distance'],
            [1609, 720, 'invalid_distance'],
            [2408, 720, 'invalid_distance'],
            ['1e999', 720, 'invalid_distance'],
            [true, 720, 'invalid_distance'],
            [2400, null, 'missing_time'],
            [2400, 0, 'invalid_time'],
            [2400, 359, 'invalid_time'],
            [2400, 2401, 'invalid_time'],
            [2400, 720.5, 'invalid_time'],
            [2400, '1e999', 'invalid_time'],
        ] as [$distance, $seconds, $status]) {
            $r = $c->evaluate('run_2400m', $distance, $seconds, 35);
            $this->assertSame($status, $r['status']);
            $this->assertNull($r['estimated_vo2_ml_kg_min']);
        }
    }
}
