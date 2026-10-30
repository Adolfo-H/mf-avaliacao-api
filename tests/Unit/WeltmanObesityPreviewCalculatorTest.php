<?php

namespace Tests\Unit;

use App\Support\WeltmanObesityPreviewCalculator;
use PHPUnit\Framework\TestCase;

final class WeltmanObesityPreviewCalculatorTest extends TestCase
{
    public function test_male_formula_matches_published_coefficients(): void
    {
        $result = WeltmanObesityPreviewCalculator::preview(
            true, 'male', 46, 98, 1.75, 110, 115, true
        );
        $expected = 0.31457 * 112.5 - 0.10969 * 98 + 10.8336;
        $this->assertSame('estimated_unvalidated', $result['status']);
        $this->assertSame(round($expected, 2), $result['percentage']);
        $this->assertSame(112.5, $result['inputs']['mean_abdomen_cm']);
        $this->assertFalse($result['homologated']);
        $this->assertFalse($result['clinical_result_saved']);
    }

    public function test_female_uses_centimeters_for_height_in_formula(): void
    {
        $result = WeltmanObesityPreviewCalculator::preview(
            true, 'female', 42, 95, 1.65, 110, 115, true
        );
        $expected = 0.11077 * 112.5 - 0.17666 * 165 + 0.14354 * 95 + 51.03301;
        $this->assertSame(round($expected, 2), $result['percentage']);
        $this->assertSame(165.0, $result['inputs']['height_cm']);
        $this->assertStringContainsString('altura (cm)', $result['formula']);
    }

    public function test_no_confirmation_never_calculates(): void
    {
        $result = WeltmanObesityPreviewCalculator::preview(true, 'male', 42, 98, 1.75, 110, 115, false);
        $this->assertSame('applicability_not_confirmed', $result['status']);
        $this->assertNull($result['percentage']);
        $this->assertFalse($result['calculated']);
    }

    public function test_wrong_protocol_never_calculates(): void
    {
        $result = WeltmanObesityPreviewCalculator::preview(false, 'male', 42, 98, 1.75, 110, 115, true);
        $this->assertSame('protocol_not_selected', $result['status']);
        $this->assertNull($result['percentage']);
    }

    public function test_no_equation_for_other_or_undisclosed_sex(): void
    {
        foreach (['other', 'not_informed', null] as $sex) {
            $result = WeltmanObesityPreviewCalculator::preview(true, $sex, 40, 98, 1.75, 110, 115, true);
            $this->assertSame('sex_not_supported', $result['status']);
            $this->assertNull($result['percentage']);
        }
    }

    public function test_children_and_missing_age_are_not_calculated(): void
    {
        foreach ([17, null] as $age) {
            $result = WeltmanObesityPreviewCalculator::preview(true, 'female', $age, 95, 1.65, 110, 115, true);
            $this->assertSame('adult_required', $result['status']);
            $this->assertNull($result['percentage']);
        }
    }

    public function test_missing_abdominal_measurement_is_not_assumed_from_waist(): void
    {
        $result = WeltmanObesityPreviewCalculator::preview(true, 'male', 40, 98, 1.75, null, 115, true);
        $this->assertSame('missing_abdominal_measurements', $result['status']);
        $this->assertNull($result['inputs']['mean_abdomen_cm']);
    }

    public function test_female_missing_height_is_not_invented(): void
    {
        $result = WeltmanObesityPreviewCalculator::preview(true, 'female', 40, 95, null, 110, 115, true);
        $this->assertSame('missing_height', $result['status']);
        $this->assertNull($result['percentage']);
    }

    public function test_out_of_physical_range_is_not_clamped(): void
    {
        $result = WeltmanObesityPreviewCalculator::preview(true, 'male', 40, 5, 1.75, 290, 290, true);
        $this->assertSame('outside_physical_range', $result['status']);
        $this->assertNull($result['percentage']);
    }

    public function test_invalid_or_missing_weight_is_rejected(): void
    {
        foreach (['foo', -20, null] as $weight) {
            $result = WeltmanObesityPreviewCalculator::preview(true, 'male', 40, $weight, 1.75, 100, 110, true);
            $this->assertSame('missing_weight', $result['status']);
            $this->assertFalse($result['calculated']);
        }
    }
}
