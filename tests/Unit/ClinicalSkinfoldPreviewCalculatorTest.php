<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ClinicalSkinfoldPreviewCalculator;
use PHPUnit\Framework\TestCase;

final class ClinicalSkinfoldPreviewCalculatorTest extends TestCase
{
    private ClinicalSkinfoldPreviewCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ClinicalSkinfoldPreviewCalculator();
    }

    public function test_seven_sites_male_uses_published_density_equation(): void
    {
        $r = $this->calculator->evaluate('pollock_7', 'male', 35, [
            'chest' => 12, 'midaxillary' => 10, 'triceps' => 8,
            'subscapular' => 12, 'abdominal' => 15,
            'suprailiac' => 11, 'thigh' => 18,
        ]);

        $this->assertSame('preview_available', $r['status']);
        $this->assertEqualsWithDelta(1.06856956, $r['body_density_preview'], 0.000002);
        $this->assertSame(13.24, $r['body_fat_percentage_preview']);
        $this->assertFalse($r['professionally_approved']);
        $this->assertFalse($r['persisted']);
        $this->assertNull($r['classification']);
    }

    public function test_three_sites_male_uses_chest_abdominal_thigh(): void
    {
        $r = $this->calculator->evaluate('pollock_3', 'male', 35, [
            'chest' => 12, 'abdominal' => 15, 'thigh' => 18,
        ]);

        $this->assertSame(['chest', 'abdominal', 'thigh'], $r['required_skinfolds']);
        $this->assertEqualsWithDelta(1.0664095, $r['body_density_preview'], 0.000002);
        $this->assertSame(14.17, $r['body_fat_percentage_preview']);
    }

    public function test_three_sites_female_uses_triceps_suprailiac_thigh(): void
    {
        $r = $this->calculator->evaluate('pollock_3', 'female', 27, [
            'triceps' => 16, 'suprailiac' => 15, 'thigh' => 20,
        ]);

        $this->assertSame(['triceps', 'suprailiac', 'thigh'], $r['required_skinfolds']);
        $this->assertEqualsWithDelta(1.0510781, $r['body_density_preview'], 0.000002);
        $this->assertSame(20.95, $r['body_fat_percentage_preview']);
    }

    public function test_seven_sites_female_has_independent_coefficients(): void
    {
        $r = $this->calculator->evaluate('pollock_7', 'female', 29, [
            'chest' => 10, 'midaxillary' => 11, 'triceps' => 14,
            'subscapular' => 12, 'abdominal' => 16,
            'suprailiac' => 13, 'thigh' => 19,
        ]);

        $this->assertEqualsWithDelta(1.05371143, $r['body_density_preview'], 0.000002);
        $this->assertSame(19.77, $r['body_fat_percentage_preview']);
    }

    public function test_missing_skinfolds_do_not_become_zero(): void
    {
        $r = $this->calculator->evaluate('pollock_3', 'male', 30, [
            'chest' => 12, 'thigh' => 18,
        ]);
        $this->assertSame('missing_measurements', $r['status']);
        $this->assertSame(['abdominal'], $r['missing_skinfolds']);
        $this->assertNull($r['body_fat_percentage_preview']);
    }

    public function test_zero_or_non_numeric_skinfolds_are_not_calculated(): void
    {
        $r = $this->calculator->evaluate('pollock_3', 'male', 30, [
            'chest' => 0, 'abdominal' => 10, 'thigh' => 'not-a-number',
        ]);
        $this->assertSame('invalid_measurements', $r['status']);
        $this->assertNull($r['body_density_preview']);
    }

    public function test_outside_original_study_age_range_is_blocked(): void
    {
        $r = $this->calculator->evaluate('pollock_3', 'female', 70, [
            'triceps' => 12, 'suprailiac' => 14, 'thigh' => 17,
        ]);
        $this->assertSame('outside_studied_age_range', $r['status']);
        $this->assertNull($r['body_fat_percentage_preview']);
    }

    public function test_unsupported_sex_is_not_assigned_an_equation(): void
    {
        $r = $this->calculator->evaluate('pollock_7', 'other', 32, []);
        $this->assertSame('unsupported_sex_for_equation', $r['status']);
        $this->assertNull($r['body_density_preview']);
    }

    public function test_unapproved_protocols_remain_pending(): void
    {
        foreach (['guedes_3', 'bioimpedance', 'weltman'] as $protocol) {
            $r = $this->calculator->evaluate($protocol, 'female', 32, []);
            $this->assertSame('pending_professional_validation', $r['status']);
            $this->assertNull($r['body_fat_percentage_preview']);
        }
    }
}
