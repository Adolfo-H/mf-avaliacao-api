<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Cooper12MinutePreviewCalculator;
use PHPUnit\Framework\TestCase;

final class Cooper12MinutePreviewCalculatorTest extends TestCase
{
    private Cooper12MinutePreviewCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new Cooper12MinutePreviewCalculator();
    }

    public function test_reference_example_2400_meters_is_42_point_35(): void
    {
        $result = $this->calculator->evaluate('cooper_12_min', 2400, 720, 32);
        $this->assertSame('preview_available', $result['status']);
        $this->assertSame(42.35, $result['estimated_vo2_ml_kg_min']);
        $this->assertSame('ml/kg/min', $result['unit']);
        $this->assertFalse($result['professionally_approved']);
        $this->assertFalse($result['persisted']);
        $this->assertNull($result['classification']);
    }

    public function test_reference_example_1500_meters_is_22_point_24(): void
    {
        $result = $this->calculator->evaluate('cooper_12_min', '1500', null, 40);
        $this->assertSame(22.24, $result['estimated_vo2_ml_kg_min']);
    }

    public function test_missing_and_invalid_measurements_never_become_zero(): void
    {
        $this->assertSame('missing_distance', $this->calculator->evaluate('cooper_12_min', null, null, 25)['status']);
        $this->assertSame('invalid_distance', $this->calculator->evaluate('cooper_12_min', 0, null, 25)['status']);
        $this->assertSame('invalid_distance', $this->calculator->evaluate('cooper_12_min', -5, null, 25)['status']);
        $this->assertSame('invalid_distance', $this->calculator->evaluate('cooper_12_min', '1e1000', null, 25)['status']);
        $this->assertSame('invalid_distance', $this->calculator->evaluate('cooper_12_min', 501, null, 25)['status']);
        $this->assertNull($this->calculator->evaluate('cooper_12_min', null, null, 25)['estimated_vo2_ml_kg_min']);
    }

    public function test_duration_must_be_twelve_minutes_when_provided(): void
    {
        $this->assertSame('invalid_duration', $this->calculator->evaluate('cooper_12_min', 2300, 600, 24)['status']);
        $this->assertSame('preview_available', $this->calculator->evaluate('cooper_12_min', 2300, 720, 24)['status']);
    }

    public function test_unspecified_or_other_protocol_is_not_estimated(): void
    {
        $this->assertSame('protocol_not_selected', $this->calculator->evaluate(null, 2400, null, 35)['status']);
        $this->assertSame('pending_professional_validation', $this->calculator->evaluate('run_2400m', 2400, 720, 35)['status']);
        $this->assertSame('pending_professional_validation', $this->calculator->evaluate('walk_1_mile', 1609, 720, 35)['status']);
        $this->assertNull($this->calculator->evaluate('run_2400m', 2400, 720, 35)['estimated_vo2_ml_kg_min']);
    }

    public function test_age_missing_or_minor_is_not_estimated(): void
    {
        $this->assertSame('missing_age', $this->calculator->evaluate('cooper_12_min', 2400, 720, null)['status']);
        $this->assertSame('outside_review_scope', $this->calculator->evaluate('cooper_12_min', 2400, 720, 17)['status']);
    }
}
