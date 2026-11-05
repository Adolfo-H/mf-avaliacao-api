<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RockportOneMilePreviewCalculator;
use PHPUnit\Framework\TestCase;

final class RockportOneMilePreviewCalculatorTest extends TestCase
{
    private RockportOneMilePreviewCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RockportOneMilePreviewCalculator();
    }

    public function test_male_reference_example_converts_kg_into_pounds_and_seconds_into_minutes(): void
    {
        $result = $this->calculator->evaluate('walk_1_mile', 1609, 900, 130, 80, 45, 'male');
        $this->assertSame('preview_available', $result['status']);
        $this->assertSame(38.84, $result['estimated_vo2_ml_kg_min']);
        $this->assertSame('ml/kg/min', $result['unit']);
        $this->assertSame(900, $result['time_seconds_used']);
        $this->assertSame(80.0, $result['weight_kg_used']);
        $this->assertFalse($result['professionally_approved']);
        $this->assertFalse($result['persisted']);
        $this->assertNull($result['classification']);
    }

    public function test_female_reference_example_has_no_male_sex_coefficient(): void
    {
        $result = $this->calculator->evaluate('walk_1_mile', 1610, 1080, 125, 68, 55, 'female');
        $this->assertSame('preview_available', $result['status']);
        $this->assertSame(21.67, $result['estimated_vo2_ml_kg_min']);
        $this->assertSame('female', $result['sex_used']);
    }

    public function test_missing_fields_do_not_return_zero_or_fabricated_result(): void
    {
        $cases = [
            ['missing_distance', 'walk_1_mile', null, 900, 120, 75, 40, 'male'],
            ['missing_time', 'walk_1_mile', 1609, null, 120, 75, 40, 'male'],
            ['missing_weight', 'walk_1_mile', 1609, 900, 120, null, 40, 'male'],
            ['missing_heart_rate', 'walk_1_mile', 1609, 900, null, 75, 40, 'male'],
            ['missing_age', 'walk_1_mile', 1609, 900, 120, 75, null, 'male'],
        ];
        foreach ($cases as [$expected, $protocol, $distance, $seconds, $hr, $kg, $age, $sex]) {
            $result = $this->calculator->evaluate($protocol, $distance, $seconds, $hr, $kg, $age, $sex);
            $this->assertSame($expected, $result['status']);
            $this->assertNull($result['estimated_vo2_ml_kg_min']);
        }
    }

    public function test_protocol_age_and_sex_limits_are_explicit(): void
    {
        $this->assertSame('protocol_not_selected', $this->calculator->evaluate(null, 1609, 900, 120, 75, 45, 'male')['status']);
        $this->assertSame('pending_professional_validation', $this->calculator->evaluate('run_2400m', 1609, 900, 120, 75, 45, 'male')['status']);
        $this->assertSame('outside_original_age_range', $this->calculator->evaluate('walk_1_mile', 1609, 900, 120, 75, 29, 'male')['status']);
        $this->assertSame('outside_original_age_range', $this->calculator->evaluate('walk_1_mile', 1609, 900, 120, 75, 70, 'female')['status']);
        $this->assertSame('sex_not_supported_by_equation', $this->calculator->evaluate('walk_1_mile', 1609, 900, 120, 75, 40, 'not_informed')['status']);
        $this->assertSame('sex_not_supported_by_equation', $this->calculator->evaluate('walk_1_mile', 1609, 900, 120, 75, 40, 'other')['status']);
    }

    public function test_distance_must_represent_a_real_mile_not_a_different_route(): void
    {
        foreach ([1500, 1600, 1700, -1, '1e1000'] as $distance) {
            $r = $this->calculator->evaluate('walk_1_mile', $distance, 900, 120, 75, 40, 'male');
            $this->assertSame('invalid_mile_distance', $r['status']);
            $this->assertNull($r['estimated_vo2_ml_kg_min']);
        }
    }

    public function test_rejects_incorrect_time_weight_or_heart_rate(): void
    {
        foreach ([0, 300, 2401, '1e1000', 900.2] as $time) {
            $this->assertSame('invalid_time', $this->calculator->evaluate('walk_1_mile', 1609, $time, 120, 75, 40, 'male')['status']);
        }
        foreach ([0, -8, 29, 301, '1e1000'] as $weight) {
            $this->assertSame('invalid_weight', $this->calculator->evaluate('walk_1_mile', 1609, 900, 120, $weight, 40, 'male')['status']);
        }
        foreach ([0, 25, 221, '1e1000', 130.5] as $heartRate) {
            $this->assertSame('invalid_heart_rate', $this->calculator->evaluate('walk_1_mile', 1609, 900, $heartRate, 75, 40, 'male')['status']);
        }
    }
}
